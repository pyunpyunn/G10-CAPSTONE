import { defaultResourceSummaryRows } from '../../utils/resourceRequestHelpers'
import SummaryTable from '../ui/SummaryTable'

export default function ResourceRequestSummaryTable({
  summary = {},
  activeStatus = 'all',
  onSelectStatus,
}) {
  const rows = summary.rows?.length ? summary.rows : defaultResourceSummaryRows(summary)

  return (
    <SummaryTable
      rows={rows}
      activeKey={activeStatus}
      onSelectRow={onSelectStatus}
    />
  )
}
