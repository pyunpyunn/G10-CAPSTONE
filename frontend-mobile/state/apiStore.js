import { configureStore, createSlice } from '@reduxjs/toolkit'
import { getAdapter, CanceledError, AxiosHeaders } from 'axios'

// Server data is session-memory only. Credentials and Axios request objects
// never enter Redux, persistence, or Redux DevTools.
const cache = createSlice({
  name: 'serverData',
  initialState: { generation: 0, session: 0, queries: {}, views: {}, network: 0, hits: 0, shared: 0, writes: 0 },
  reducers: {
    reset(state) { state.generation++; state.session++; state.queries = {}; state.views = {} },
    invalidate(state, { payload: paths }) {
      state.generation++
      if (!paths) state.views = {}
      for (const [key, entry] of Object.entries(state.queries)) {
        if (!paths || paths.some(path => entry.path.includes(path))) delete state.queries[key]
      }
    },
    requested(state) { state.network++ },
    hit(state) { state.hits++ },
    shared(state) { state.shared++ },
    wrote(state) { state.writes++ },
    savedView(state, { payload }) {
      while (Object.keys(state.views).length >= 30) delete state.views[Object.keys(state.views)[0]]
      state.views[payload.key] = { value: payload.value, savedAt: Date.now() }
    },
    removedView(state, { payload }) {
      for (const key of Object.keys(state.views)) if (key.startsWith(payload)) delete state.views[key]
    },
    received(state, { payload }) {
      if (payload.generation !== state.generation) return
      for (const [key, entry] of Object.entries(state.queries)) {
        if (entry.expires <= Date.now()) delete state.queries[key]
      }
      while (Object.keys(state.queries).length >= 50) delete state.queries[Object.keys(state.queries)[0]]
      state.queries[payload.key] = payload.entry
    },
  },
})

export const store = configureStore({ reducer: { serverData: cache.reducer }, devTools: false })
export const resetApiState = () => store.dispatch(cache.actions.reset())
export const invalidateApiState = (paths) => store.dispatch(cache.actions.invalidate(paths))
export const saveViewState = (key, value) => store.dispatch(cache.actions.savedView({ key, value }))
export const getViewState = (key) => {
  const view = store.getState().serverData.views[key]
  return view && Date.now() - view.savedAt < 120000 ? view : null
}
export const removeViewState = (prefix) => store.dispatch(cache.actions.removedView(prefix))

function canonical(value) {
  if (value instanceof URLSearchParams) return value.toString()
  if (Array.isArray(value)) return value.map(canonical)
  if (value && typeof value === 'object') return Object.fromEntries(Object.keys(value).sort().map(key => [key, canonical(value[key])]))
  return value
}

function duration(path) {
  if (/\/(auth|mobile\/bootstrap|global-search)(\/|$)|\/radio|\/tracking|\/dispatch-routes|\/route($|\?)/.test(path)) return 0
  if (/options|status-types|disaster-types|resource-request-types/.test(path)) return 60000
  if (/overview|dashboard|assignments|dispatches|map\//.test(path)) return 3000
  return 10000
}

export function installReduxTransport(client) {
  const adapter = getAdapter(client.defaults.adapter)
  const inFlight = new Map()
  let scope
  client.defaults.adapter = async config => {
    const nextScope = `${config.baseURL || ''}|${config.headers?.get?.('Authorization') || config.headers?.Authorization || ''}|${config.headers?.get?.('X-Device-UUID') || ''}`
    if (scope !== nextScope) { scope = nextScope; resetApiState(); inFlight.clear() }
    const session = store.getState().serverData.session
    const path = config.url || ''
    const method = String(config.method || 'get').toLowerCase()
    if (method !== 'get') {
      const response = await adapter(config)
      if (session !== store.getState().serverData.session) throw new CanceledError('The account session changed.')
      store.dispatch(cache.actions.wrote())
      // Frequent location and radio heartbeats must not evict unrelated forms.
      invalidateApiState(/\/location$|device-location$/.test(path) ? ['map/', '/route', '/dispatch']
        : /\/radio\//.test(path) ? ['/radio', '/communications'] : undefined)
      return response
    }
    const ttl = duration(path)
    if (!ttl || config.responseType && config.responseType !== 'json' || config.cache === false) {
      store.dispatch(cache.actions.requested())
      const response = await adapter(config)
      if (session !== store.getState().serverData.session) throw new CanceledError('The account session changed.')
      return response
    }
    const generation = store.getState().serverData.generation
    const key = JSON.stringify([config.baseURL, path, canonical(config.params), config.responseType, config.headers?.get?.('Accept-Language')])
    const entry = store.getState().serverData.queries[key]
    if (entry && entry.expires > Date.now()) {
      if (config.signal?.aborted) throw new CanceledError()
      store.dispatch(cache.actions.hit())
      return { ...entry.response, data: typeof entry.response.data === 'object' ? JSON.parse(JSON.stringify(entry.response.data)) : entry.response.data, config }
    }
    const flightKey = `${generation}:${key}`
    let pending = inFlight.get(flightKey)
    if (pending) store.dispatch(cache.actions.shared())
    else {
      store.dispatch(cache.actions.requested())
      // One cancelled component must not cancel another consumer's same read.
      pending = adapter({ ...config, signal: undefined, cancelToken: undefined }).then(response => {
        const data = response.data
        const serialized = typeof data === 'string' ? data : JSON.stringify(data)
        if (response.status >= 200 && response.status < 300 && serialized?.length < 512000) {
          store.dispatch(cache.actions.received({ generation, key, entry: { path, expires: Date.now() + ttl,
            response: { data, status: response.status, statusText: response.statusText, headers: AxiosHeaders.from(response.headers).toJSON() } } }))
        }
        return response
      })
      inFlight.set(flightKey, pending)
      pending.then(() => inFlight.delete(flightKey), () => inFlight.delete(flightKey))
    }
    if (config.signal?.aborted) throw new CanceledError()
    let cancel
    const cancelled = new Promise((_, reject) => {
      cancel = () => reject(new CanceledError())
      config.signal?.addEventListener('abort', cancel, { once: true })
    })
    try {
      const response = await Promise.race([pending, cancelled])
      if (session !== store.getState().serverData.session) throw new CanceledError('The account session changed.')
      return { ...response, config, data: typeof response.data === 'object' ? JSON.parse(JSON.stringify(response.data)) : response.data }
    } finally { config.signal?.removeEventListener('abort', cancel) }
  }
}
