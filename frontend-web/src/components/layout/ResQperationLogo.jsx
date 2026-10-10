import resqperationLogo from '../../assets/resqperation-logo.png'
import resqperationIcon from '../../assets/resqperation-icon.png'

export default function ResQperationLogo() {
  return (
    <>
      <img src={resqperationLogo} alt="ResQperation" className="left-sidebar-brand-img left-sidebar-brand-full" />
      <img src={resqperationIcon} alt="ResQperation" className="left-sidebar-brand-img left-sidebar-brand-icon" />
    </>
  )
}
