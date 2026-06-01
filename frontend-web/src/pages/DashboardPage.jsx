import { CloudSun, Map, PackageCheck, Settings, TriangleAlert } from 'lucide-react'
import { useNavigate } from 'react-router-dom'

const stats = [
  { label: 'Total households', value: '500', sub: 'Bgy. Sta. Cruz' },
  { label: 'Unchecked', value: '215', sub: '43% no report yet', tone: 'slate' },
  { label: 'Safe total', value: '230', sub: 'Safe 175 + Evac 55', tone: 'green' },
  { label: 'Unsafe / missing', value: '55', sub: 'Needs dispatch', tone: 'red' },
  { label: 'Evacuated', value: '55', sub: 'In evac centers', tone: 'blue' },
]

const householdBars = [
  { label: 'Safe total', value: 230, height: '92%', className: 'safe-total' },
  { label: 'Safe only', value: 175, height: '70%', className: 'safe-only' },
  { label: 'Evacuated', value: 55, height: '22%', className: 'evacuated' },
  { label: 'Unsafe', value: 55, height: '22%', className: 'unsafe' },
  { label: 'Unchecked', value: 215, height: '86%', className: 'unchecked' },
]

const dispatchBars = [
  { label: 'Dispatched', value: 3, height: '75%', className: 'dispatched' },
  { label: 'On-scene', value: 4, height: '100%', className: 'on-scene' },
  { label: 'Stand-by', value: 1, height: '25%', className: 'standby' },
]

const teams = [
  ['Search & Rescue', 8, 'On-scene', 'Purok 3 - Zone B', '06:14 AM', 'green', '#7c3aed'],
  ['Evacuation', 7, 'On-scene', 'Purok 4 - All zones', '06:10 AM', 'green', '#2563eb'],
  ['Medical / First Aid', 6, 'Dispatched', 'Purok 5 - Zone A', '06:20 AM', 'purple', '#dc2626'],
  ['Relief & Transport', 7, 'On-scene', 'Purok 1 - Zone C', '06:08 AM', 'green', '#16a34a'],
  ['Communication', 5, 'Stand-by', 'HQ radio desk', '-', 'gray', '#d97706'],
  ['Security', 6, 'Dispatched', 'All puroks', '06:05 AM', 'purple', '#64748b'],
]

const logs = [
  ['06:22', '#16a34a', 'Dela Cruz Family (Purok 3)', 'Safe', 'green'],
  ['06:20', '#dc2626', 'Reyes Family (Purok 5)', 'Unsafe', 'red'],
  ['06:18', '#2563eb', 'Santos Family (Purok 4)', 'Evacuated', 'blue'],
  ['06:14', '#7c3aed', 'SAR Team 1', 'Dispatched', 'purple'],
  ['06:10', '#16a34a', 'Bautista Family (Purok 1)', 'Safe', 'green'],
]

const requests = [
  ['Purok 3 HH', 'Food packs', '12', 'Pending', 'amber'],
  ['SAR Team 1', 'Life jackets', '4', 'Forwarded', 'blue'],
  ['MDRRMO', 'Evac tent', '2', 'Onboarding', 'green'],
  ['Medical Team', 'IV fluids', '10', 'Pending', 'amber'],
]

export default function DashboardPage() {
  const navigate = useNavigate()

  function openModule(path) {
    navigate(path)
  }

  return (
    <section className="page active">
      <div className="pg-head">
        <div>
          <h1 className="pg-title">Dashboard</h1>
          <p className="pg-sub">Barangay Sta. Cruz - command overview</p>
        </div>
        <div className="pg-actions">
          <button className="btn btn-danger btn-sm dashboard-declare-button" type="button" onClick={() => openModule('/broadcast')}>
            <TriangleAlert size={14} />
            Declare New Disaster
          </button>
        </div>
      </div>

      <div className="dashboard-layout">
        <div className="dashboard-main">
          <div className="alert-banner danger">
            <span className="pulse" />
            <span>Active event - Typhoon Carina - Puroks 3, 4, 5 - Declared 6:00 AM - PSWS No. 3</span>
            <button className="alert-action" type="button" onClick={() => openModule('/broadcast')}>View details -&gt;</button>
          </div>

          <div className="stat-row">
            {stats.map((stat) => (
              <div className={`stat-card ${stat.tone ? `c-${stat.tone}` : ''}`} key={stat.label}>
                <div className="lbl">{stat.label}</div>
                <div className="val">{stat.value}</div>
                <div className="sub">{stat.sub}</div>
              </div>
            ))}
          </div>

          <div className="progress-label">
            <span>Reporting progress</span>
            <span>57% reported (285 / 500)</span>
          </div>
          <div className="prog-track">
            <div className="prog-seg green" style={{ width: '35%' }} />
            <div className="prog-seg blue" style={{ width: '11%' }} />
            <div className="prog-seg red" style={{ width: '11%' }} />
          </div>

          <div className="panel">
            <div className="panel-head"><span className="panel-title">Operational charts</span></div>
            <div className="dashboard-dispatch-graphs">
              <ChartCard
                title="Household status"
                bars={householdBars}
                axis={['250', '200', '150', '100', '0']}
                onManage={() => openModule('/households')}
              />
              <ChartCard
                title="Dispatch status - team count"
                bars={dispatchBars}
                axis={['4', '3', '2', '1', '0']}
                onManage={() => openModule('/dispatch')}
              />
            </div>
          </div>

          <div className="panel">
            <div className="panel-head">
              <span className="panel-title">Team dispatch overview</span>
              <button className="panel-link" type="button" onClick={() => openModule('/dispatch')}>Full dispatch -&gt;</button>
            </div>
            <div className="tbl-wrap compact-table">
              <table className="dispatch-overview-table">
                <thead><tr><th>Team</th><th>Members</th><th>Status</th><th>Assigned area</th><th>Dispatched at</th></tr></thead>
                <tbody>
                  {teams.map((team) => (
                    <tr key={team[0]}>
                      <td><span className="team-dot" style={{ background: team[6] }} />{team[0]}</td>
                      <td>{team[1]}</td>
                      <td><span className={`badge b-${team[5]}`}>{team[2]}</span></td>
                      <td>{team[3]}</td>
                      <td>{team[4]}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          <div className="sep">Recent activity log <span>(paginated - showing latest events)</span></div>
          <div className="tbl-wrap">
            <div className="log-list">
              {logs.map((log) => (
                <div className="log-item" key={`${log[0]}-${log[2]}`}>
                  <span className="log-time">{log[0]}</span>
                  <span className="log-dot" style={{ background: log[1] }} />
                  <div className="log-msg">
                    {log[2]} - <span className={`badge b-${log[4]}`}>{log[3]}</span>
                  </div>
                </div>
              ))}
            </div>
            <div className="table-footer">
              <span>Only the latest records load first to keep 1,000+ household operations fast.</span>
              <button className="btn btn-secondary btn-sm" type="button">View all logs</button>
            </div>
          </div>
        </div>

        <aside className="dashboard-overview" aria-label="System overview">
          <div className="overview-heading">
            <span className="overview-heading-title">System overview</span>
            <span className="badge b-red">Active</span>
          </div>

          <section className="overview-card">
            <div className="panel-head">
              <span className="panel-title"><CloudSun size={15} />Disaster alert update</span>
              <button className="panel-link" type="button" onClick={() => openModule('/weather')}>Full view -&gt;</button>
            </div>
            <div className="wx-card">
              <div className="wx-source">PAGASA - PSWS No. 3</div>
              <div className="wx-msg">Typhoon Carina intensifying. Heavy rainfall and strong winds. Low-lying areas must evacuate immediately.</div>
              <div className="wx-time">May 27, 2026 - 5:00 AM - Source: PAGASA feed</div>
            </div>
            <div className="trusted-row" aria-label="Trusted advisory sources">
              <span className="trusted-chip">PAGASA</span>
              <span className="trusted-chip">NDRRMC</span>
              <span className="trusted-chip">Open-Meteo</span>
            </div>
          </section>

          <section className="overview-card">
            <div className="panel-head">
              <span className="panel-title"><Map size={15} />Map display</span>
              <button className="panel-link" type="button" onClick={() => openModule('/mapping')}>Full view -&gt;</button>
            </div>
            <button className="overview-map map-mock" type="button" onClick={() => openModule('/mapping')} aria-label="Open full map display">
              <div className="map-road-h one" />
              <div className="map-road-v" />
              <div className="team-route" />
              <span className="evac-pin first" />
              <span className="evac-pin second" />
              <span className="overview-dot safe dot-a" />
              <span className="overview-dot safe dot-b" />
              <span className="overview-dot evac dot-c" />
              <span className="overview-dot unsafe dot-d" />
              <span className="overview-dot unchecked dot-e" />
              <span className="overview-map-label">Open full map</span>
            </button>
            <div className="overview-map-stats">
              <div className="overview-mini-stat"><strong>2</strong><span>Evac sites</span></div>
              <div className="overview-mini-stat"><strong>55</strong><span>Unsafe</span></div>
              <div className="overview-mini-stat"><strong>215</strong><span>Unchecked</span></div>
            </div>
          </section>

          <section className="overview-card">
            <div className="panel-head">
              <span className="panel-title"><PackageCheck size={15} />Requests</span>
              <button className="panel-link" type="button" onClick={() => openModule('/resources-requests')}>Full view -&gt;</button>
            </div>
            <div className="requests-summary">
              <div className="request-summary-item"><strong>2</strong><span>Pending</span></div>
              <div className="request-summary-item"><strong>1</strong><span>Forwarded</span></div>
              <div className="request-summary-item"><strong>1</strong><span>Onboarding</span></div>
            </div>
            <div className="overview-request-table">
              <table>
                <thead><tr><th>Request from</th><th>Request</th><th>Qty</th><th>Status</th></tr></thead>
                <tbody>
                  {requests.map((request) => (
                    <tr key={`${request[0]}-${request[1]}`}>
                      <td>{request[0]}</td>
                      <td>{request[1]}</td>
                      <td>{request[2]}</td>
                      <td><span className={`badge b-${request[4]}`}>{request[3]}</span></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>
        </aside>
      </div>
    </section>
  )
}

function ChartCard({ title, bars, axis, onManage }) {
  return (
    <div className="dispatch-chart">
      <div className="dispatch-chart-head">
        <div className="dispatch-chart-title">{title}</div>
        <button className="chart-manage-button" type="button" aria-label={`Manage ${title}`} onClick={onManage}>
          <Settings size={15} />
        </button>
      </div>
      <div className="team-count-chart" role="img" aria-label={title}>
        <div className="chart-y-axis" aria-hidden="true">
          {axis.map((item) => <span key={item}>{item}</span>)}
        </div>
        <div className="chart-plot" style={{ '--bar-count': bars.length }}>
          {bars.map((bar) => (
            <div className="dispatch-bar" style={{ '--bar-height': bar.height }} key={bar.label}>
              <span className="dispatch-bar-value">{bar.value}</span>
              <span className={`dispatch-bar-fill ${bar.className}`} />
              <span className="dispatch-bar-label">{bar.label}</span>
            </div>
          ))}
        </div>
      </div>
    </div>
  )
}
