import { createApi } from '@reduxjs/toolkit/query/react'

export const operationTags = ['Dispatch', 'Households', 'Disasters', 'Accounts', 'FieldReports', 'Resources', 'Archives', 'Reports', 'Mapping', 'Weather', 'Notifications']
export function tagsForPaths(paths) {
  if (!paths) return operationTags
  const tags = new Set()
  for (const path of paths) {
    if (/dispatch|rescue-teams/.test(path)) tags.add('Dispatch')
    if (/household|member/.test(path)) tags.add('Households')
    if (/disaster|broadcast/.test(path)) tags.add('Disasters')
    if (/rescuers|personnel|accounts|profile/.test(path)) tags.add('Accounts')
    if (/field-reports/.test(path)) tags.add('FieldReports')
    if (/resource-request/.test(path)) { tags.add('Resources'); tags.add('Archives'); tags.add('Reports') }
    if (/archive/.test(path)) tags.add('Archives')
    if (/situation|reports/.test(path)) tags.add('Reports')
    if (/\/map\//.test(path)) tags.add('Mapping')
    if (/weather/.test(path)) tags.add('Weather')
    if (/notification/.test(path)) tags.add('Notifications')
  }
  return [...tags]
}

export const operationsApi = createApi({
  reducerPath: 'operationsApi',
  tagTypes: operationTags,
  keepUnusedDataFor: 60,
  refetchOnMountOrArgChange: 15,
  refetchOnFocus: true,
  refetchOnReconnect: true,
  baseQuery: async ({ url, params }, { signal }) => {
    try {
      const { default: client } = await import('../api/client.js')
      // RTK Query owns this cache; do not put another TTL cache beneath it.
      const response = await client.get(url, { params, cache: false, signal })
      if (!response.data || typeof response.data !== 'object' || !Object.hasOwn(response.data, 'data')) {
        return { error: { status: 'INVALID_RESPONSE', message: 'The server returned an unexpected response. Records could not be verified. Please refresh or contact the administrator.' } }
      }
      return { data: response.data.data }
    } catch (failure) {
      return { error: { status: failure.response?.status || 'NETWORK_ERROR',
        message: failure.response?.data?.message || 'Records could not be loaded. Please try again.' } }
    }
  },
  endpoints: builder => ({
    workspace: builder.query({
      query: ({ view, ...params }) => ({ url: view === 'reports' ? '/field-reports' : '/dispatches',
        params: view === 'reports' ? { include_summary: 0, ...params } : { view, ...params } }),
      providesTags: (_result, _error, { view }) => view === 'reports'
        ? ['FieldReports','Dispatch','Households','Disasters']
        : view === 'coverage' ? ['Dispatch','Accounts']
          : ['Dispatch','Households','Disasters','Accounts'],
    }),
    resourceRequests: builder.query({
      query: params => ({ url: '/resource-requests', params }),
      providesTags: ['Resources', 'Disasters'],
    }),
    archiveRecords: builder.query({
      query: ({ category, ...params }) => ({ url: `/archive/${category}`, params }),
      providesTags: ['Archives', 'Disasters', 'Resources', 'Dispatch', 'FieldReports', 'Households'],
    }),
  }),
})

export const { useWorkspaceQuery, useResourceRequestsQuery, useArchiveRecordsQuery } = operationsApi
