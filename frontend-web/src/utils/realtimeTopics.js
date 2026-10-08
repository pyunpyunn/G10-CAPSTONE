export const operationTopics = ['households', 'mapping', 'dispatch', 'field-reports', 'communications', 'requests', 'weather', 'disasters', 'inquiries', 'accounts']

export function topicsForPath(path, superAdmin = false) {
  const root = path.split('/')[1]
  const topics = {
    dashboard: ['households', 'mapping', 'dispatch', 'weather', 'requests', 'disasters'],
    households: ['households', 'disasters'],
    mapping: ['mapping', 'households', 'dispatch', 'disasters'],
    dispatch: ['dispatch', 'households', 'communications', 'disasters'],
    'rescue-management': ['dispatch', 'households', 'disasters'],
    'field-reports': ['field-reports', 'households', 'dispatch', 'disasters'],
    'super-admin': ['inquiries', 'accounts'],
    weather: ['weather', 'disasters'],
    broadcast: ['disasters'],
  }[root] || operationTopics
  return topics.filter((topic) => superAdmin || !['inquiries', 'accounts'].includes(topic))
}
