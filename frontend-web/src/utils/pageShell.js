export function getPageLoadState(isLoading, hasData) {
  return {
    isInitialLoading: isLoading && !hasData,
    isRefreshing: isLoading && Boolean(hasData),
  }
}

export function pageDataError(error, hasData) {
  return error && !hasData ? error : ''
}
