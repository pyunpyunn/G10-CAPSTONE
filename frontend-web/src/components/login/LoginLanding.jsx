import {
  CloudSun,
  Database,
  House,
  LockKeyhole,
  Map,
  Radio,
  Route,
  ShieldUser,
} from 'lucide-react'

const featureCards = [
  {
    title: 'Disaster broadcasting',
    text: 'Critical alerts, purok targeting, evacuation instructions, and mobile notification support.',
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
    text: 'Disaster duration, casualties, requests, resources, damage notes, and situation reports.',
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

          <button className="primary-button" type="button" onClick={onOpenLogin}>
            Sign in
          </button>
        </div>
      </header>

      <HeroSection onOpenLogin={onOpenLogin} />
      <WorkflowSection />
      <BasisSection />
      <FeatureSection />
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
          <StepCard number="03" title="Collect reports" text="Track household status reports from verified accounts and authenticated responders." />
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
          <p>Local risk information, warning, response coordination, and recovery reporting guide the workflow.</p>
        </div>

        <div className="pillar-grid">
          <StepCard number="PM" title="Prevention and Mitigation" text="Risk maps and archived records help identify repeated vulnerabilities." />
          <StepCard number="PR" title="Preparedness" text="Broadcasts, evacuation routes, resource visibility, and assigned accounts support readiness." />
          <StepCard number="RS" title="Response" text="Dispatch tracking connects household reports with rescue teams, medical aid, evacuation, and resources." />
          <StepCard number="RR" title="Rehabilitation and Recovery" text="Situation reporting and archives preserve casualties, damage, resource actions, and timelines." />
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
                <div className="contact-icon"><LockKeyhole size={18} /></div>
                <div>
                  <strong>Credential-based access only</strong>
                  <span>No public registration. Accounts are role-based.</span>
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
        <span>RESQPERATION capstone prototype. Original UI visuals, no public registration.</span>
        <div className="footer-links" aria-label="Reference links">
          <a href="#basis">RA 10121</a>
          <a href="#basis">NDRRMP 2020-2030</a>
          <a href="#features">PAGASA</a>
          <a href="#features">SDG 11</a>
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
