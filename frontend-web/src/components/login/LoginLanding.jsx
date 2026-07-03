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
    title: 'Disaster Broadcasting',
    text: 'Broadcast critical alerts, target specific puroks, issue evacuation instructions, and send mobile push notifications.',
    Icon: Radio,
  },
  {
    title: 'Weather and Alert Updates',
    text: 'Monitor PAGASA weather updates and government advisories with centralized warning tracking.',
    Icon: CloudSun,
  },
  {
    title: 'Mapping',
    text: 'Visualize evacuation sites, rescue routes, and household status indicators on barangay maps.',
    Icon: Map,
  },
  {
    title: 'Household Status',
    text: 'Monitor household safety status, including safe, evacuated, injured, missing, and unverified cases.',
    Icon: House,
  },
  {
    title: 'Rescue Dispatch',
    text: 'Manage team assignments, dispatch status, field requests, and response outcomes linked to household reports',
    Icon: Route,
  },
  {
    title: 'Archive and Reports',
    text: 'Generate situation reports and maintain historical records of casualties, injuries, missing persons, property damage, and response activities.',
    Icon: Database,
  },
]

export default function LoginLanding({ onOpenLogin }) {
  return (
    <main className="landing-page" id="top">
      <header className="landing-header">
        <div className="landing-wrap landing-header-inner">
          <a className="landing-brand" href="#top" aria-label="RESQPERATION landing page">
            <span className="brand-mark">
              <img className="brand-logo-image" src="/favicon.svg" alt="" aria-hidden="true" />
            </span>
            <span className="landing-brand-text">
              <strong>RESQPERATION</strong>
              <span>COMMAND CENTER</span>
            </span>
          </a>

          <nav className="landing-nav" aria-label="Landing navigation">
            <a href="#workflow">Workflow</a>
            <a href="#basis">Framework</a>
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
        <p className="eyebrow">Barangay DRRM Command Center</p>
        <h1>RESQPERATION</h1>
        <p className="hero-tagline">Command Center operations organized from alert to archive.</p>
        <p className="hero-copy">
          RESQPERATION is a role-based barangay command center platform: broadcast official
          alerts, monitor household and responder safety, assign field teams, manage requests,
          and archive incident records for accountability and recovery.
        </p>

        <div className="hero-cta">
          <button className="primary-button" type="button" onClick={onOpenLogin}>
            Open command center
          </button>
          <a className="secondary-button" href="#workflow">See how it works</a>
        </div>

        <div className="basis-row" aria-label="System basis">
          <a className="basis-chip" href="https://elibrary.judiciary.gov.ph/thebookshelf/showdocs/2/21121" target="_blank" rel="noreferrer">RA 10121 aligned</a>
          <a className="basis-chip" href="https://www.preventionweb.net/publication/policies-and-plans/philippines-national-disaster-risk-reduction-and-management-plan" target="_blank" rel="noreferrer">NDRRMP pillars</a>
          <a className="basis-chip" href="https://pagasa.dost.gov.ph/products-and-services" target="_blank" rel="noreferrer">PAGASA alert context</a>
          <a className="basis-chip" href="https://sdgs.un.org/goals/goal11" target="_blank" rel="noreferrer">SDG 11 support</a>
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
          <h2>How the command center streamlines disaster response</h2>
        </div>

        <div className="workflow-grid">
          <StepCard number="01" title="Activate incident" text="Create an incident record, assign operational roles, and initiate the command center response cycle." />
          <StepCard number="02" title="Issue alerts" text="Broadcast official alerts and advisories to households, responders, and designated purok zones." />
          <StepCard number="03" title="Monitor status" text="Monitor household and responder status, including safe, evacuated, injured, missing, and unverified cases." />
          <StepCard number="04" title="Coordinate response" text="Assign teams, track field requests, update resources, and close the incident with a situation report." />
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
          <h2>Aligned with Philippine DRRM Frameworks and Practices</h2>
        </div>

        <div className="pillar-grid">
          <StepCard number="PM" title="Prevention and Mitigation" text="Risk mapping, household data analysis, and archived incident records help identify recurring vulnerabilities." />
          <StepCard number="PR" title="Preparedness" text="Alert broadcasts, evacuation planning, resource visibility, and role-based user access support preparedness." />
          <StepCard number="RS" title="Response" text="Dispatch tracking connects household reports with rescue teams, medical aid, evacuation, and resources." />
          <StepCard number="RR" title="Rehabilitation and Recovery" text="Situation reports and archived records document casualties, damages, resource deployments, and recovery timelines." />
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
            <p>SDG 11 promotes safer, more resilient communities through disaster risk reduction and preparedness. RESQPERATION supports this goal by helping command centers manage alerts, response operations, evacuation monitoring, and post-disaster records.</p>
          </div>
          <div className="sdg-list">
            <div>
              <strong>Local strategy support</strong>
              Integrates household reporting, mapping, dispatch operations, and incident records into a unified barangay-level workflow.
            </div>
            <div>
              <strong>Inclusive response view</strong>
              Helps command center personnel identify unsafe households and prioritize urgent status reports during operations.
            </div>
            <div>
              <strong>Evidence for planning</strong>
              Archived incident records support future drills, resource planning, and evacuation improvements.
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
          <h2 id="visuals-title">Operational Dashboard Views</h2>
        </div>

        <div className="photo-grid">
          <article className="soft-card visual-card">
            <div className="visual-scene command" role="img" aria-label="Original command desk dashboard visual">
              <div className="screen-block" />
            </div>
            <div className="visual-card-content">
              <h3>Command Desk View</h3>
              <p>Monitor incident status, resource availability, requests, and dispatch activities from a centralized command dashboard.</p>
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
              <h3>Evacuation and Routing</h3>
              <p>Visualize evacuation sites, response routes, and household locations on a clear and scalable barangay map.</p>
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
              <h3>Mobile Status Reports</h3>
              <p>Households and responders submit field reports that automatically update the command center dashboard.</p>
            </div>
          </article>
        </div>
      </div>
    </section>
  )
}

function ContactSection() {
  function submitInquiry(event) {
    event.preventDefault()

    const data = new FormData(event.currentTarget)
    const name = data.get('name') || ''
    const organization = data.get('organization') || ''
    const email = data.get('email') || ''
    const message = data.get('message') || ''
    const subject = encodeURIComponent('RESQPERATION inquiry')
    const body = encodeURIComponent(
      `Name: ${name}\nOrganization: ${organization}\nEmail: ${email}\n\nMessage:\n${message}`,
    )

    window.location.href = `mailto:resqperation.devteam@example.com?subject=${subject}&body=${body}`
  }

  return (
    <section className="section" id="contact">
      <div className="landing-wrap">
        <div className="contact-panel">
          <div className="soft-card">
            <p className="eyebrow">Get In Touch</p>
            <h2>Contact Us</h2>
            <p>Have questions about RESQPERATION? Reach out to discuss system capabilities, research objectives, implementation opportunities, or request a live demonstration.</p>
            <div className="contact-list">
              <div className="contact-item">
                <div className="contact-icon"><ShieldUser size={18} /></div>
                <div>
                  <strong>Project Information</strong>
                  <span>Questions regarding system features, documentation, architecture, and research objectives.</span>
                </div>
              </div>
              <div className="contact-item">
                <div className="contact-icon"><Radio size={18} /></div>
                <div>
                  <strong>System demo and coordination</strong>
                  <span>Available for BDRRMO and command center walkthroughs. Learn how RESQPERATION supports barangay disaster risk reduction and management operations.</span>
                </div>
              </div>
            </div>
          </div>

          <form className="soft-card contact-form" onSubmit={submitInquiry}>
            <div className="form-row">
            <strong>Full Name</strong>
            <strong>Organization</strong>
            </div>
            <div className="form-row">
              <input type="text" name="name" placeholder="Enter your FullName" aria-label="Name" />
              <input type="text" name="organization" placeholder="Enter your Organization" aria-label="Organization" />
            </div>
            <strong>Email Address </strong>
            <input type="email" name="email" placeholder="Enter your Email" aria-label="Email" />
            <strong>Tell us about your Inquiry</strong>
            <textarea name="message" placeholder="Enter your Message" aria-label="Message" />
            <button className="primary-button" type="submit">Send inquiry</button>
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
        <span>Copyright © 2026 RESQPERATION All Right Reserved</span>
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
