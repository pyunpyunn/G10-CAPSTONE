// Immediate first update, then at most one trailing refresh per interval during bursts.
export function createRefreshScheduler(callback, interval = 1250) {
  let lastRun = -Infinity
  let timer
  let disposed = false
  const run = () => {
    timer = undefined
    if (disposed) return
    lastRun = Date.now()
    callback()
  }
  return {
    request() {
      if (disposed || timer !== undefined) return
      const delay = Math.max(0, interval - (Date.now() - lastRun))
      if (delay === 0) run()
      else timer = setTimeout(run, delay)
    },
    cancel() {
      disposed = true
      clearTimeout(timer)
    },
  }
}
