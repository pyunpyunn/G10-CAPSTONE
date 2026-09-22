import {
  Document,
  Page,
  StyleSheet,
  Text,
  View,
} from '@react-pdf/renderer'

const colors = {
  primary: '#164e73',
  ink: '#182533',
  muted: '#637384',
  border: '#cbd5df',
  surface: '#f5f8fb',
  white: '#ffffff',
  safe: '#18794e',
  warn: '#9a6700',
  danger: '#b42318',
}

const styles = StyleSheet.create({
  page: {
    padding: 0,
    color: colors.ink,
    backgroundColor: colors.white,
    fontFamily: 'Helvetica',
    fontSize: 8.5,
  },
  report: {
    border: `1pt solid ${colors.border}`,
    minHeight: '100%',
  },
  header: {
    padding: '20pt 24pt 16pt',
    borderTop: `4pt solid ${colors.primary}`,
    borderBottom: `1pt solid ${colors.border}`,
    textAlign: 'center',
    backgroundColor: colors.white,
  },
  republic: {
    color: colors.muted,
    fontSize: 7,
    letterSpacing: 1,
    textTransform: 'uppercase',
  },
  office: {
    marginTop: 3,
    fontSize: 9,
    fontFamily: 'Helvetica-Bold',
    lineHeight: 1.35,
  },
  title: {
    marginTop: 14,
    color: colors.primary,
    fontSize: 16,
    fontFamily: 'Helvetica-Bold',
    textTransform: 'uppercase',
  },
  eventTitle: {
    marginTop: 4,
    fontSize: 10,
    fontFamily: 'Helvetica-Bold',
  },
  metaGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    borderBottom: `1pt solid ${colors.border}`,
    backgroundColor: colors.surface,
  },
  meta: {
    width: '25%',
    minHeight: 38,
    padding: '8pt 10pt',
    borderRight: `1pt solid ${colors.border}`,
    borderBottom: `1pt solid ${colors.border}`,
  },
  metaLabel: {
    marginBottom: 3,
    color: colors.muted,
    fontSize: 6.5,
    textTransform: 'uppercase',
  },
  metaValue: {
    fontSize: 8,
    fontFamily: 'Helvetica-Bold',
  },
  section: {
    padding: '14pt 18pt 16pt',
    borderBottom: `1pt solid ${colors.border}`,
  },
  sectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 9,
  },
  sectionNumber: {
    width: 23,
    height: 21,
    marginRight: 8,
    paddingTop: 6,
    borderRadius: 3,
    color: colors.white,
    backgroundColor: colors.primary,
    fontSize: 7,
    fontFamily: 'Helvetica-Bold',
    textAlign: 'center',
  },
  sectionTitle: {
    color: colors.ink,
    fontSize: 9,
    fontFamily: 'Helvetica-Bold',
    textTransform: 'uppercase',
  },
  cardGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    borderTop: `1pt solid ${colors.border}`,
    borderLeft: `1pt solid ${colors.border}`,
  },
  card: {
    width: '20%',
    minHeight: 43,
    padding: '8pt 9pt',
    borderRight: `1pt solid ${colors.border}`,
    borderBottom: `1pt solid ${colors.border}`,
  },
  cardHalf: {
    width: '50%',
  },
  cardQuarter: {
    width: '25%',
  },
  cardLabel: {
    marginBottom: 4,
    color: colors.muted,
    fontSize: 6.5,
  },
  cardValue: {
    fontSize: 9,
    fontFamily: 'Helvetica-Bold',
  },
  note: {
    marginTop: 9,
    padding: '8pt 10pt',
    borderLeft: `2pt solid ${colors.primary}`,
    color: colors.muted,
    backgroundColor: colors.surface,
    fontSize: 7.5,
    lineHeight: 1.4,
  },
  table: {
    marginTop: 4,
    borderTop: `1pt solid ${colors.border}`,
    borderLeft: `1pt solid ${colors.border}`,
  },
  tableRow: {
    flexDirection: 'row',
  },
  tableHeader: {
    color: colors.muted,
    backgroundColor: colors.surface,
    fontFamily: 'Helvetica-Bold',
    textTransform: 'uppercase',
  },
  tableCell: {
    flex: 1,
    minHeight: 22,
    padding: '6pt 6pt',
    borderRight: `1pt solid ${colors.border}`,
    borderBottom: `1pt solid ${colors.border}`,
    fontSize: 7,
  },
  actionBox: {
    minHeight: 100,
    padding: 10,
    border: `1pt solid ${colors.border}`,
    color: colors.ink,
    fontSize: 8.5,
    lineHeight: 1.5,
  },
})

export default function SitrepPdfDocument({ summary, includedSections = [], actionsText = '' }) {
  const data = summary || {}
  const event = data.event || {}
  const report = data.report || {}

  return (
    <Document title={`${report.report_number || 'SitRep draft'} - ${event.name || 'Disaster event'}`}>
      <Page size="A4" style={styles.page} wrap>
        <View style={styles.report}>
          <View style={styles.header}>
            <Text style={styles.republic}>Republic of the Philippines</Text>
            <Text style={styles.office}>Barangay Disaster Risk Reduction and Management Committee{String.fromCharCode(10)}Emergency Operations Center</Text>
            <Text style={styles.title}>Situation Report</Text>
            <Text style={styles.eventTitle}>{text(event.name)}</Text>
          </View>

          <View style={styles.metaGrid}>
            <Meta label="Disaster type" value={event.type} />
            <Meta label="Date declared" value={event.declared_at} />
            <Meta label="Date finished" value={event.finished_at} />
            <Meta label="Alert level" value={event.severity} />
            <Meta label="Reporting period" value={`${text(report.period_start)} - ${text(report.period_end)}`} />
            <Meta label="Area coverage" value={event.coverage} />
            <Meta label="Situation status" value={event.situation_status} />
            <Meta label="Prepared by" value={report.prepared_by} />
          </View>

          {includedSections.map((section) => renderSection(section, data, actionsText))}
        </View>
      </Page>
    </Document>
  )
}

function renderSection(section, data, actionsText) {
  const household = data.household || {}
  const casualties = data.casualties || {}
  const weather = data.weather || {}

  switch (section) {
    case 'I':
      return <PdfSection key={section} number="I" title="Situation Overview"><CardGrid items={[['Condition', weather.condition], ['Wind', weather.wind], ['Rainfall', weather.rainfall], ['Temperature', weather.temperature], ['Source', weather.source]]} /><Note>{weather.advisory}</Note></PdfSection>
    case 'II':
      return <PdfSection key={section} number="II" title="Affected Population"><CardGrid items={[['Total HH affected', household.total], ['Safe total', household.safe_total], ['Evacuated', household.evacuated], ['Unsafe / At risk', household.unsafe], ['Unchecked', household.unchecked], ['Deaths', casualties.deaths], ['Missing', casualties.missing], ['Injured', casualties.injured]]} cardStyle={styles.cardQuarter} /><Note>Reporting progress: {text(household.progress_text)} checked - {text(household.progress_sub)}</Note><PurokTable rows={household.puroks || []} /></PdfSection>
    case 'III':
      return <PdfSection key={section} number="III" title="Casualties and Immediate Needs"><CardGrid items={[['Deaths', `${text(casualties.deaths)} confirmed`], ['Missing', `${text(casualties.missing)} persons`], ['Injured', `${text(casualties.injured)} persons`], ['Rescued', `${text(casualties.rescued)} persons assisted`]]} cardStyle={styles.cardQuarter} /></PdfSection>
    case 'IV':
      return <PdfSection key={section} number="IV" title="Evacuation Centers"><SimpleRows rows={data.evacuation || []} fields={['name', 'type', 'status', 'capacity_status']} /></PdfSection>
    case 'V':
      return <PdfSection key={section} number="V" title="Rescue Operations Timeline"><SimpleRows rows={data.dispatch?.timeline || []} fields={['time', 'title', 'actor', 'status']} /><SimpleRows rows={data.dispatch?.rows || []} fields={['team', 'deployed_at', 'area', 'outcomes']} /></PdfSection>
    case 'VI':
      return <PdfSection key={section} number="VI" title="Initial Damage Assessment"><CardGrid items={[['Partially damaged houses', data.damage?.partial], ['Totally damaged houses', data.damage?.total]]} cardStyle={styles.cardHalf} /></PdfSection>
    case 'VII':
      return <PdfSection key={section} number="VII" title="Resources Deployed and Requests"><SimpleRows rows={data.resources?.rows || []} fields={['item', 'quantity', 'source', 'status']} /></PdfSection>
    case 'VIII':
      return <PdfSection key={section} number="VIII" title="Actions Taken and Recommendations"><View style={styles.actionBox}><Text>{actionsText || ' '}</Text></View></PdfSection>
    default:
      return null
  }
}

function PdfSection({ number, title, children }) {
  return <View style={styles.section} wrap={false}><View style={styles.sectionHeader}><Text style={styles.sectionNumber}>{number}</Text><Text style={styles.sectionTitle}>{title}</Text></View>{children}</View>
}

function Meta({ label, value }) {
  return <View style={styles.meta}><Text style={styles.metaLabel}>{label}</Text><Text style={styles.metaValue}>{text(value)}</Text></View>
}

function CardGrid({ items, cardStyle }) {
  return <View style={styles.cardGrid}>{items.map(([label, value]) => <View key={label} style={[styles.card, cardStyle]}><Text style={styles.cardLabel}>{label}</Text><Text style={styles.cardValue}>{text(value)}</Text></View>)}</View>
}

function Note({ children }) {
  return <Text style={styles.note}>{children}</Text>
}

function PurokTable({ rows }) {
  return <Table headers={['Purok', 'Households', 'Safe', 'Evacuated', 'Unsafe', 'Unchecked']} rows={rows.map((row) => [row.purok, row.total, row.safe, row.evacuated, row.unsafe, row.unchecked])} />
}

function SimpleRows({ rows, fields }) {
  return <Table headers={fields} rows={rows.map((row) => fields.map((field) => row[field]))} />
}

function Table({ headers, rows }) {
  return <View style={styles.table}><View style={[styles.tableRow, styles.tableHeader]}>{headers.map((header) => <Text style={styles.tableCell} key={header}>{header}</Text>)}</View>{rows.length === 0 ? <View style={styles.tableRow}><Text style={styles.tableCell}>No records linked to this event.</Text></View> : rows.map((row, rowIndex) => <View style={styles.tableRow} key={`${rowIndex}-${row[0]}`}>{row.map((value, cellIndex) => <Text style={styles.tableCell} key={`${rowIndex}-${cellIndex}`}>{text(value)}</Text>)}</View>)}</View>
}

function text(value) {
  return value === null || value === undefined || value === '' ? '-' : String(value)
}
