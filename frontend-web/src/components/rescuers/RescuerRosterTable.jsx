import { MoreHorizontal } from 'lucide-react'
import ActionMenu from '../ui/ActionMenu'
import Badge from '../ui/Badge'
import EmptyState from '../ui/EmptyState'

export default function RescuerRosterTable({ rescuers, pagination, onPageChange, onView, onEdit, onDeactivate }) {
  const from = pagination?.from || 0
  const to = pagination?.to || 0
  const total = pagination?.total || 0
  const currentPage = pagination?.current_page || 1
  const lastPage = pagination?.last_page || 1

  return (
    <div className="ra-panel">
      <div className="ra-panel-head">
        <span className="ra-title">Verified roster</span>
        <span className="ra-subtle">{total ? `Showing ${from}-${to} of ${total}` : 'No records yet'}</span>
      </div>
      <div className="ra-table-wrap">
        {rescuers.length === 0 ? (
          <EmptyState title="No rescuer accounts found" message="Create verified accounts or try another filter." />
        ) : (
          <table className="ra-table">
            <thead>
              <tr>
                <th>Responder</th>
                <th>Team / role</th>
                <th>Contact / ICE</th>
                <th>Duty status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {rescuers.map((rescuer) => (
                <tr key={rescuer.responder_id}>
                  <td>
                    <div className="ra-person">{rescuer.full_name}</div>
                    <div className="ra-meta">{rescuer.responder_code || rescuer.account_id} - {rescuer.address || 'No address recorded'}</div>
                  </td>
                  <td>
                    <strong>{rescuer.team_name}</strong>
                    <div className="ra-meta">{rescuer.title}</div>
                  </td>
                  <td>
                    <span className="ra-mono">{rescuer.contact_number || '-'}</span>
                    <div className="ra-meta">ICE: {rescuer.emergency_contact_name || 'Not recorded'}</div>
                  </td>
                  <td><Badge tone={rescuer.duty_status.tone}>{rescuer.duty_status.label}</Badge></td>
                  <td>
                    <div className="ra-actions">
                      <ActionMenu
                        label={`Responder actions for ${rescuer.full_name}`}
                        buttonClassName="row-action-button"
                        icon={<MoreHorizontal size={16} />}
                        actions={[
                          { label: 'View', onClick: () => onView(rescuer) },
                          { label: 'Edit', onClick: () => onEdit(rescuer) },
                          { label: 'Deactivate', onClick: () => onDeactivate(rescuer) },
                        ]}
                      />
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
      {total > 0 && (
        <div className="ra-roster-pagination">
          <span>Page {currentPage} of {lastPage}</span>
          <div>
            <button type="button" disabled={currentPage <= 1} onClick={() => onPageChange(currentPage - 1)}>Previous</button>
            <button type="button" disabled={currentPage >= lastPage} onClick={() => onPageChange(currentPage + 1)}>Next</button>
          </div>
        </div>
      )}
    </div>
  )
}
