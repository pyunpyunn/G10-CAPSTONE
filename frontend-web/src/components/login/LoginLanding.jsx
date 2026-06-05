import {
  CloudSun,
  Database,
  House,
  Map,
  Radio,
  Route,
  ShieldUser,
} from 'lucide-react'

const featureCards = [
  {
    title: 'Disaster broadcasting',
    text: 'Critical alerts, purok targeting, evacuation instructions, and mobile push notification support.',
    Icon: Radio,
  },
  {
    title: 'Weather and alert updates',
    text: 'Designed for PAGASA and government advisory context with full-view tracking for warnings.',
    Icon: CloudSun,
  },
  {
    title: 'Mapping',
    text: 'Evacuation sites, rescue routes, and compact household status dots for dense barangay maps.',
    Icon: Map,
  },
  {
    title: 'Household status',
    text: 'Reports and analytics for safe, evacuated, unsafe, injured, missing, and unchecked households.',
    Icon: House,
  },
  {
    title: 'Rescue dispatch',
    text: 'Team assignments, dispatch status, outcomes, and field requests connected to household reports.',
    Icon: Route,
  },
  {
    title: 'Archive and reports',
    text: 'Disaster duration, casualties, missing and injured counts, property damage, and situation reports.',
    Icon: Database,
  },
]

export default function LoginLanding({ onOpenLogin }) {
  return (
    <main className="landing-page" id="top">
      <header className="landing-header">
        <div className="landing-wrap landing-header-inner">
          <a className="landing-brand" href="#top" aria-label="RESQPERATION landing page">
            <span className="brand-mark">R</span>
            <span className="landing-brand-text">
              <strong>RESQPERATION</strong>
              <span>Barangay rescue operations</span>
            </span>
          </a>

          <nav className="landing-nav" aria-label="Landing navigation">
            <a href="#workflow">Workflow</a>
            <a href="#basis">Basis</a>
            <a href="#features">Features</a>
            <a href="#contact">Contact</a>
          </nav>

          <div className="landing-actions">
            <button className="primary-button" type="button" onClick={onOpenLogin}>
              Sign in
            </button>
          </div>
        </div>
      </header>

      <HeroSection onOpenLogin={onOpenLogin} />
      <WorkflowSection />
      <BasisSection />
      <SdgSection />
      <FeatureSection />
      <VisualPanelsSection />
      <ContactSection />
      <LandingFooter />
    </main>
  )
}

function HeroSection({ onOpenLogin }) {
  return (
    <section className="landing-wrap landing-hero">
      <div>
        <p className="eyebrow">Barangay DRRM command support</p>
        <h1>RESQPERATION</h1>
        <p className="hero-tagline">Barangay rescue operations, organized from alert to archive.</p>
        <p className="hero-copy">
          RESQPERATION is a role-based system for barangay response headquarters: broadcast
          official alerts, monitor household safety status, dispatch rescue teams, track resources,
          and archive disaster records after operations.
        </p>

        <div className="hero-cta">
          <button className="primary-button" type="button" onClick={onOpenLogin}>
            Access command dashboard
          </button>
          <a className="secondary-button" href="#workflow">See how it works</a>
        </div>

        <div className="basis-row" aria-label="System basis">
          <span className="basis-chip">RA 10121 aligned</span>
          <span className="basis-chip">NDRRMP pillars</span>
          <span className="basis-chip">PAGASA alert context</span>
          <span className="basis-chip">SDG 11 support</span>
        </div>
      </div>

      <div className="hero-visual" role="img" aria-label="Barangay response dashboard and household map visual">
        <div className="map-route">
          <span className="map-pin" />
          <span className="map-pin red" />
          <span className="map-pin" />
          <span className="map-pin gray" />
          <span className="map-pin" />
          <span className="map-pin red" />
        </div>

        <div className="visual-panel dispatch-panel">
          <div className="panel-kicker">Dispatch status</div>
          <div className="dispatch-team"><strong>Search & Rescue</strong><span>En route</span></div>
          <div className="dispatch-team"><strong>Evacuation</strong><span>Assigned</span></div>
          <div className="dispatch-team"><strong>Medical Aid</strong><span>Ready</span></div>
        </div>

        <div className="visual-panel status-panel">
          <div className="panel-kicker">Household reports</div>
          <div className="panel-value">64%</div>
          <div className="panel-line"><span>Accounted households</span><strong>320/500</strong></div>
          <div className="mini-bars" aria-hidden="true">
            <span />
            <span />
            <span />
          </div>
        </div>
      </div>
    </section>
  )
}

function WorkflowSection() {
  return (
    <section className="section" id="workflow">
      <div className="landing-wrap">
        <div className="section-heading">
          <h2>How the system supports a barangay rescue operation</h2>
          <p>Each step turns field updates into information the command desk can act on quickly.</p>
        </div>

        <div className="workflow-grid">
          <StepCard number="01" title="Declare event" text="Open an active disaster record and reset household status to unchecked for a clean operation cycle." />
          <StepCard number="02" title="Broadcast alert" text="Send barangay-wide or purok-specific instructions to households and rescue mobile users." />
          <StepCard number="03" title="Collect reports" text="Track safe, evacuated, unsafe, injured, missing, and unchecked households from verified accounts." />
          <StepCard number="04" title="Dispatch and archive" text="Assign teams, monitor requests and resources, then close the event with a situation report." />
        </div>
      </div>
    </section>
  )
}

function BasisSection() {
  return (
    <section className="section" id="basis">
      <div className="landing-wrap">
        <div className="section-heading">
          <h2>Built around Philippine DRRM practice</h2>
          <p>The landing copy is based on official DRRM roles, local risk information, early warning, response coordination, and recovery reporting.</p>
        </div>

        <div className="pillar-grid">
          <StepCard number="PM" title="Prevention and Mitigation" text="Risk maps, household status patterns, and archived records help identify repeated vulnerabilities." />
          <StepCard number="PR" title="Preparedness" text="Broadcasts, evacuation routes, resource visibility, and assigned accounts support readiness." />
          <StepCard number="RS" title="Response" text="Dispatch tracking connects household reports with rescue teams, medical aid, evacuation, and resources." />
          <StepCard number="RR" title="Rehabilitation and Recovery" text="Situation reporting and archives preserve casualties, damage, resource actions, and timelines." />
        </div>
      </div>
    </section>
  )
}

function SdgSection() {
  return (
    <section className="section" aria-labelledby="sdg-title">
      <div className="landing-wrap">
        <div className="sdg-band">
          <div>
            <div className="sdg-mark">11</div>
            <h2 id="sdg-title">Supports SDG 11: resilient communities</h2>
            <p>SDG 11 includes local disaster risk reduction strategies. RESQPERATION supports that goal by helping barangays organize response data, warnings, evacuation status, and post-disaster records.</p>
          </div>
          <div className="sdg-list">
            <div>
              <strong>Local strategy support</strong>
              Links household reporting, mapping, dispatching, and records in one barangay-level workflow.
            </div>
            <div>
              <strong>Inclusive response view</strong>
              Helps the command desk see unchecked households and urgent status reports during operations.
            </div>
            <div>
              <strong>Evidence for planning</strong>
              Archived events can support future drills, resource requests, and evacuation improvements.
            </div>
          </div>
        </div>
      </div>
    </section>
  )
}

function FeatureSection() {
  return (
    <section className="section" id="features">
      <div className="landing-wrap">
        <div className="section-heading">
          <h2>Feature overview</h2>
          <p>Simple modules for the work a barangay response headquarters needs during and after a disaster.</p>
        </div>

        <div className="feature-grid">
          {featureCards.map((feature) => (
            <article className="soft-card feature-card" key={feature.title}>
              <div className="feature-icon"><feature.Icon size={22} /></div>
              <div>
                <h3>{feature.title}</h3>
                <p>{feature.text}</p>
              </div>
            </article>
          ))}
        </div>
      </div>
    </section>
  )
}

function VisualPanelsSection() {
  return (
    <section className="section" aria-labelledby="visuals-title">
      <div className="landing-wrap">
        <div className="section-heading">
          <h2 id="visuals-title">Operational picture panels</h2>
          <p>Original prototype visuals are used here instead of copyrighted photographs or agency logos.</p>
        </div>

        <div className="photo-grid">
          <article className="soft-card visual-card">
            <div className="visual-scene command" role="img" aria-label="Original command desk dashboard visual">
              <div className="screen-block" />
            </div>
            <div className="visual-card-content">
              <h3>Command desk view</h3>
              <p>Dashboard status, requests, resources, and dispatch activity in one headquarters view.</p>
            </div>
          </article>
          <article className="soft-card visual-card">
            <div className="visual-scene evacuation" role="img" aria-label="Original evacuation route map visual">
              <div className="route-block">
                <span className="route-dot one" />
                <span className="route-dot two" />
                <span className="route-dot three" />
              </div>
            </div>
            <div className="visual-card-content">
              <h3>Evacuation and routing</h3>
              <p>Compact pins and route lines keep maps usable even with many households.</p>
            </div>
          </article>
          <article className="soft-card visual-card">
            <div className="visual-scene mobile" role="img" aria-label="Original mobile household status report visual">
              <div className="phone-block">
                <span className="phone-line" />
                <span className="phone-line" />
                <span className="phone-line" />
                <span className="phone-line" />
              </div>
            </div>
            <div className="visual-card-content">
              <h3>Mobile status reports</h3>
              <p>Households and rescuers submit field information that updates the command dashboard.</p>
            </div>
          </article>
        </div>
      </div>
    </section>
  )
}

function ContactSection() {
  return (
    <section className="section" id="contact">
      <div className="landing-wrap">
        <div className="contact-panel">
          <div className="soft-card">
            <p className="eyebrow">Reach out</p>
            <h2>Contact the developers</h2>
            <p>For demo access, capstone review, or barangay workflow notes, contact the RESQPERATION development team.</p>
            <div className="contact-list">
              <div className="contact-item">
                <div className="contact-icon"><ShieldUser size={18} /></div>
                <div>
                  <strong>Capstone Development Team</strong>
                  <span>resqperation.devteam@example.com</span>
                </div>
              </div>
              <div className="contact-item">
                <div className="contact-icon"><Radio size={18} /></div>
                <div>
                  <strong>System demo and coordination</strong>
                  <span>Available for BDRRMO and barangay response walkthroughs</span>
                </div>
              </div>
            </div>
          </div>

          <form className="soft-card contact-form">
            <div className="form-row">
              <input type="text" name="name" placeholder="Name" aria-label="Name" />
              <input type="text" name="organization" placeholder="Organization" aria-label="Organization" />
            </div>
            <input type="email" name="email" placeholder="Email" aria-label="Email" />
            <textarea name="message" placeholder="Message" aria-label="Message" />
            <button className="primary-button" type="button">Send inquiry</button>
          </form>
        </div>
      </div>
    </section>
  )
}

function LandingFooter() {
  return (
    <footer className="landing-footer">
      <div className="landing-wrap footer-inner">
        <span>RESQPERATION capstone prototype. Original UI visuals, no public registration, credential-based access only.</span>
        <div className="footer-links" aria-label="Reference links">
          <a href="https://elibrary.judiciary.gov.ph/thebookshelf/showdocs/2/21121" target="_blank" rel="noreferrer">RA 10121</a>
          <a href="https://www.preventionweb.net/publication/policies-and-plans/philippines-national-disaster-risk-reduction-and-management-plan" target="_blank" rel="noreferrer">NDRRMP 2020-2030</a>
          <a href="https://pagasa.dost.gov.ph/products-and-services" target="_blank" rel="noreferrer">PAGASA</a>
          <a href="https://sdgs.un.org/goals/goal11" target="_blank" rel="noreferrer">SDG 11</a>
        </div>
      </div>
    </footer>
  )
}

function StepCard({ number, title, text }) {
  return (
    <article className="soft-card">
      <div className="step-number">{number}</div>
      <h3>{title}</h3>
      <p>{text}</p>
    </article>
  )
}
