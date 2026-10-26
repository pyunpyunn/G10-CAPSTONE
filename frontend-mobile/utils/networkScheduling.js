// Coalesce concurrent reads; a refresh after a write runs once after any older read.
export function createRefreshQueue() {
  let active = null
  let queued = null
  let latestTask = null
  function run(task, queueLatest = false) {
    if (active) {
      if (!queueLatest) return queued || active
      latestTask = task
      if (!queued) {
        queued = active.catch(() => {}).then(() => {
          const next = latestTask
          queued = null
          latestTask = null
          return run(next)
        })
      }
      return queued
    }
    const pending = Promise.resolve().then(task)
    active = pending
    pending.then(() => { if (active === pending) active = null }, () => { if (active === pending) active = null })
    return pending
  }
  return { run }
}

export function createAdaptivePoller(task, { intervalMs, maxIntervalMs }) {
  let timer = null
  let stopped = true
  let delay = intervalMs

  function schedule() {
    if (stopped) return
    timer = setTimeout(() => {
      timer = null
      Promise.resolve().then(task).then(
        result => { delay = result == null || result === false ? Math.min(delay * 2, maxIntervalMs) : intervalMs },
        () => { delay = Math.min(delay * 2, maxIntervalMs) },
      ).finally(schedule)
    }, delay)
  }

  function stop() {
    stopped = true
    if (timer) clearTimeout(timer)
    timer = null
  }

  return {
    start() {
      if (stopped) {
        stopped = false
        delay = intervalMs
        schedule()
      }
      return stop
    },
  }
}

// Race connection routes instead of waiting for each slow route in sequence.
export function firstSuccessful(jobs) {
  if (!jobs.length) return Promise.reject(new Error('No connection routes configured.'))
  const controllers = jobs.map(() => new AbortController())
  return new Promise((resolve, reject) => {
    let remaining = jobs.length
    let settled = false
    jobs.forEach((job, index) => {
      Promise.resolve().then(() => job(controllers[index].signal)).then(value => {
        if (settled) return
        settled = true
        controllers.forEach((controller, other) => { if (other !== index) controller.abort() })
        resolve(value)
      }, error => {
        remaining--
        if (!remaining && !settled) reject(error)
      })
    })
  })
}
