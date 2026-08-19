import {
  Building2,
  CloudSun,
  Database,
  House,
  Mail,
  Map,
  MessageSquare,
  Radio,
  Route,
  Send,
  ShieldUser,
  UserRound,
} from 'lucide-react'
import { useCallback, useState } from 'react'
import { submitLandingInquiry } from '../../api/inquiryApi'

const navItems = [
  { id: 'workflow', label: 'Workflow' },
  { id: 'basis', label: 'Framework' },
  { id: 'features', label: 'Features' },
  { id: 'contact', label: 'Contact' },
]

function scrollToLandingSection(sectionId) {
  if (sectionId === 'top') {
    window.scrollTo({ top: 0, behavior: 'smooth' })
    return
  }

  const target = document.getElementById(sectionId)

  if (!target) {
    return
  }

  const header = document.querySelector('.landing-header')
  const offset = (header?.offsetHeight || 70) + 16
  const top = window.scrollY + target.getBoundingClientRect().top - offset

  window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' })
}

const featureCards = [
  { title: 'Alerts', text: 'Broadcast and target barangay zones.', Icon: Radio },
  { title: 'Weather', text: 'Track advisories in one place.', Icon: CloudSun },
  { title: 'Maps', text: 'Households, routes, and evacuation sites.', Icon: Map },
  { title: 'Households', text: 'Live safety status from residents.', Icon: House },
  { title: 'Dispatch', text: 'Assign teams and track progress.', Icon: Route },
  { title: 'Archive', text: 'SitReps and incident history.', Icon: Database },
]

export default function LoginLanding({ onOpenLogin }) {
  const handleNavClick = useCallback((event, sectionId) => {
    event.preventDefault()
    scrollToLandingSection(sectionId)
  }, [])

  return (
    <main className="landing-page" id="top">
      <header className="landing-header">
        <div className="landing-wrap landing-header-inner">
          <a
            className="landing-brand"
            href="#top"
            aria-label="RESQPERATION landing page"
            onClick={(event) => handleNavClick(event, 'top')}
          >
            <span className="brand-mark">
              <img className="brand-logo-image" src="/favicon.svg" alt="" aria-hidden="true" />
            </span>
            <span className="landing-brand-text">
              <strong>RESQPERATION</strong>
              <span>COMMAND CENTER</span>
            </span>
          </a>

          <nav className="landing-nav" aria-label="Landing navigation">
            {navItems.map((item) => (
              <a
                key={item.id}
                href={`#${item.id}`}
                onClick={(event) => handleNavClick(event, item.id)}
              >
                {item.label}
              </a>
            ))}
          </nav>

          <div className="landing-actions">
            <button className="primary-button" type="button" onClick={onOpenLogin}>
              Sign in
            </button>
          </div>
        </div>
      </header>

      <HeroSection onOpenLogin={onOpenLogin} onNavigate={handleNavClick} />
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

function HeroSection({ onOpenLogin, onNavigate }) {
  return (
    <section className="landing-wrap landing-hero">
      <div>
        <p className="eyebrow">Barangay command center</p>
        <h1>RESQPERATION</h1>
        <p className="hero-tagline">Alerts, households, dispatch, and records in one place.</p>

        <div className="hero-cta">
          <button className="primary-button" type="button" onClick={onOpenLogin}>
            Sign in
          </button>
          <a
            className="secondary-button"
            href="#workflow"
            onClick={(event) => onNavigate(event, 'workflow')}
          >
            How it works
          </a>
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
          <h2>Response workflow</h2>
        </div>

        <div className="workflow-grid">
          <StepCard number="01" title="Activate" text="Open an incident and assign roles." />
          <StepCard number="02" title="Alert" text="Send instructions to households and teams." />
          <StepCard number="03" title="Monitor" text="Track household and field status." />
          <StepCard number="04" title="Close" text="Dispatch teams and file the SitRep." />
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
          <h2>DRRM alignment</h2>
        </div>

        <div className="pillar-grid">
          <StepCard number="PM" title="Prevention" text="Risk data and archived incidents." />
          <StepCard number="PR" title="Preparedness" text="Alerts, plans, and role access." />
          <StepCard number="RS" title="Response" text="Dispatch linked to household reports." />
          <StepCard number="RR" title="Recovery" text="SitReps and post-event records." />
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
            <h2 id="sdg-title">Supports SDG 11</h2>
            <p>Helps barangays run safer, better-prepared disaster operations.</p>
          </div>
          <div className="sdg-list">
            <div>
              <strong>Unified workflow</strong>
              Households, maps, dispatch, and records together.
            </div>
            <div>
              <strong>Priority view</strong>
              See unsafe households first during operations.
            </div>
            <div>
              <strong>Planning data</strong>
              Archived events support future drills and plans.
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
          <h2 id="visuals-title">Dashboard views</h2>
        </div>

        <div className="photo-grid">
          <article className="soft-card visual-card">
            <div className="visual-scene command" role="img" aria-label="Original command desk dashboard visual">
              <div className="screen-block" />
            </div>
            <div className="visual-card-content">
              <h3>Command desk</h3>
              <p>Incident status, requests, and dispatch at a glance.</p>
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
              <h3>Evacuation map</h3>
              <p>Centers, routes, and household locations.</p>
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
              <h3>Mobile reports</h3>
              <p>Household and field updates sync to HQ.</p>
            </div>
          </article>
        </div>
      </div>
    </section>
  )
}

function ContactSection() {
  const [status, setStatus] = useState('')
  const [statusTone, setStatusTone] = useState('info')
  const [isSending, setIsSending] = useState(false)

  async function submitInquiry(event) {
    event.preventDefault()
    setStatus('')
    setIsSending(true)

    const data = new FormData(event.currentTarget)
    const payload = {
      name: String(data.get('name') || '').trim(),
      organization: String(data.get('organization') || '').trim(),
      email: String(data.get('email') || '').trim(),
      message: String(data.get('message') || '').trim(),
    }

    if (!payload.name || !payload.message) {
      setStatusTone('error')
      setStatus('Full name and inquiry message are required.')
      setIsSending(false)
      return
    }

    try {
      await submitLandingInquiry(payload)
      event.currentTarget.reset()
      setStatusTone('success')
      setStatus('Your inquiry was sent. HQ admin can review it in the Inquiries page.')
    } catch (error) {
      setStatusTone('error')
      setStatus(error?.response?.data?.message || 'Inquiry cannot be saved right now. Please try again later.')
    } finally {
      setIsSending(false)
    }
  }

  return (
    <section className="section landing-contact-section" id="contact">
      <div className="landing-wrap">
        <div className="landing-contact-grid">
          <div className="landing-contact-intro soft-card">
            <p className="eyebrow">Contact</p>
            <h2>Send a message</h2>

            <div className="landing-contact-points">
              <div className="landing-contact-point">
                <div className="landing-contact-point-icon"><ShieldUser size={18} /></div>
                <div>
                  <strong>Project questions</strong>
                  <span>Modules, research, and documentation.</span>
                </div>
              </div>
              <div className="landing-contact-point">
                <div className="landing-contact-point-icon"><Radio size={18} /></div>
                <div>
                  <strong>Demo requests</strong>
                  <span>Walkthroughs for BDRRMO teams.</span>
                </div>
              </div>
            </div>

            <div className="landing-contact-note">
              Visible to HQ admin only.
            </div>
          </div>

          <form className="soft-card landing-inquiry-form" onSubmit={submitInquiry}>
            <div className="landing-inquiry-form-head">
              <MessageSquare size={18} />
              <div>
                <strong>Inquiry</strong>
                <span>* required fields</span>
              </div>
            </div>

            <div className="form-row">
              <label className="landing-field">
                <span><UserRound size={14} /> Full name *</span>
                <input type="text" name="name" placeholder="Enter your full name" aria-label="Full name" required />
              </label>
              <label className="landing-field">
                <span><Building2 size={14} /> Organization</span>
                <input type="text" name="organization" placeholder="Barangay, school, or agency" aria-label="Organization" />
              </label>
            </div>

            <label className="landing-field">
              <span><Mail size={14} /> Email address</span>
              <input type="email" name="email" placeholder="you@example.com" aria-label="Email address" />
            </label>

            <label className="landing-field">
              <span><MessageSquare size={14} /> Inquiry message *</span>
              <textarea
                name="message"
                placeholder="Tell us what you want to know about RESQPERATION..."
                aria-label="Inquiry message"
                required
              />
            </label>

            {status && (
              <div className={`landing-form-note ${statusTone === 'success' ? 'is-success' : 'is-error'}`}>
                {status}
              </div>
            )}

            <button className="primary-button landing-inquiry-submit" type="submit" disabled={isSending}>
              <Send size={16} />
              {isSending ? 'Sending inquiry...' : 'Send inquiry'}
            </button>
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
