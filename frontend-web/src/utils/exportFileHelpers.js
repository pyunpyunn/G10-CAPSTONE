export function downloadBlob(fileName, blob) {
  const url = window.URL.createObjectURL(blob)
  const link = document.createElement('a')

  link.href = url
  link.download = fileName
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.setTimeout(() => window.URL.revokeObjectURL(url), 0)
}

export function parseCsvText(text) {
  const csvText = String(text || '').replace(/^\uFEFF/, '')
  const rows = []
  let row = []
  let value = ''
  let inQuotes = false

  for (let index = 0; index < csvText.length; index += 1) {
    const char = csvText[index]
    const nextChar = csvText[index + 1]

    if (char === '"' && inQuotes && nextChar === '"') {
      value += '"'
      index += 1
    } else if (char === '"') {
      inQuotes = !inQuotes
    } else if (char === ',' && !inQuotes) {
      row.push(value)
      value = ''
    } else if ((char === '\n' || char === '\r') && !inQuotes) {
      if (char === '\r' && nextChar === '\n') {
        index += 1
      }

      row.push(value)
      rows.push(row)
      row = []
      value = ''
    } else {
      value += char
    }
  }

  if (value || row.length) {
    row.push(value)
    rows.push(row)
  }

  return rows.filter((item) => item.some((cell) => String(cell).trim() !== ''))
}

export function downloadExcelWorkbook(fileName, title, rows) {
  const safeRows = normalizeRows(rows)
  const html = `
    <html>
      <head>
        <meta charset="UTF-8" />
        <style>
          body { font-family: Arial, sans-serif; }
          h1 { font-size: 18px; margin: 0 0 12px; }
          table { border-collapse: collapse; width: 100%; }
          th, td { border: 1px solid #d0d9e4; padding: 8px; font-size: 12px; text-align: left; vertical-align: top; }
          th { background: #edf1f6; font-weight: 700; }
        </style>
      </head>
      <body>
        <h1>${escapeHtml(title)}</h1>
        <table>
          ${safeRows.map((row, rowIndex) => `
            <tr>
              ${row.map((cell) => rowIndex === 0
                ? `<th>${escapeHtml(cell)}</th>`
                : `<td>${escapeHtml(cell)}</td>`).join('')}
            </tr>
          `).join('')}
        </table>
      </body>
    </html>
  `
  const blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8;' })

  downloadBlob(forceExtension(fileName, 'xls'), blob)
}

export function downloadPdfReport(fileName, title, rows) {
  const blob = makePdfBlob(title, rows)

  downloadBlob(forceExtension(fileName, 'pdf'), blob)
}

export function detailsToRows(details = []) {
  return [
    ['Field', 'Value'],
    ...details.map((item) => [item.label, item.value]),
  ]
}

export function cellText(value) {
  if (value === null || value === undefined || value === '') {
    return ''
  }

  if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
    return String(value)
  }

  if (value.label) {
    return value.label
  }

  if (value.title || value.meta) {
    return [value.title, value.meta].filter(Boolean).join(' - ')
  }

  return String(value)
}

function normalizeRows(rows = []) {
  const cleanRows = rows.length ? rows : [['No records'], ['No records to export']]
  const width = Math.max(...cleanRows.map((row) => row.length))

  return cleanRows.map((row) => {
    const nextRow = row.map(cellText)

    while (nextRow.length < width) {
      nextRow.push('')
    }

    return nextRow
  })
}

function makePdfBlob(title, rows) {
  const safeRows = normalizeRows(rows)
  const reportLines = [
    title,
    `Generated: ${new Date().toLocaleString('en-PH', { timeZone: 'Asia/Manila' })}`,
    '',
    ...safeRows.map((row) => row.map(cellText).join(' | ')),
  ]
  const wrappedLines = reportLines.flatMap((line) => wrapLine(toPdfSafeText(line), 94))
  const pageLines = chunk(wrappedLines, 48)
  const objects = ['', '', '']
  const pageRefs = []

  objects[1] = '<< /Type /Catalog /Pages 2 0 R >>'
  objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'

  pageLines.forEach((lines) => {
    const content = makePdfPageContent(lines)
    const contentId = objects.length
    objects.push(`<< /Length ${content.length} >>\nstream\n${content}\nendstream`)

    const pageId = objects.length
    objects.push(`<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R >> >> /Contents ${contentId} 0 R >>`)
    pageRefs.push(`${pageId} 0 R`)
  })

  objects[2] = `<< /Type /Pages /Kids [${pageRefs.join(' ')}] /Count ${pageRefs.length} >>`

  return new Blob([buildPdf(objects)], { type: 'application/pdf' })
}

function makePdfPageContent(lines) {
  const textLines = lines.map((line) => `(${escapePdfText(line)}) Tj T*`).join('\n')

  return `BT\n/F1 10 Tf\n50 762 Td\n12 TL\n${textLines}\nET`
}

function buildPdf(objects) {
  let body = '%PDF-1.4\n'
  const offsets = [0]

  for (let index = 1; index < objects.length; index += 1) {
    offsets[index] = body.length
    body += `${index} 0 obj\n${objects[index]}\nendobj\n`
  }

  const xrefStart = body.length
  body += `xref\n0 ${objects.length}\n0000000000 65535 f \n`

  for (let index = 1; index < objects.length; index += 1) {
    body += `${String(offsets[index]).padStart(10, '0')} 00000 n \n`
  }

  body += `trailer\n<< /Size ${objects.length} /Root 1 0 R >>\nstartxref\n${xrefStart}\n%%EOF`

  return body
}

function wrapLine(line, maxLength) {
  if (!line) {
    return ['']
  }

  const words = String(line).split(' ')
  const lines = []
  let current = ''

  words.forEach((word) => {
    const next = current ? `${current} ${word}` : word

    if (next.length > maxLength) {
      if (current) {
        lines.push(current)
      }
      current = word
    } else {
      current = next
    }
  })

  if (current) {
    lines.push(current)
  }

  return lines
}

function chunk(items, size) {
  const chunks = []

  for (let index = 0; index < items.length; index += size) {
    chunks.push(items.slice(index, index + size))
  }

  return chunks.length ? chunks : [['No records to export']]
}

function forceExtension(fileName, extension) {
  return `${String(fileName || 'resqperation-export').replace(/\.[^.]+$/, '')}.${extension}`
}

function escapeHtml(value) {
  return cellText(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;')
}

function escapePdfText(value) {
  return String(value)
    .replaceAll('\\', '\\\\')
    .replaceAll('(', '\\(')
    .replaceAll(')', '\\)')
}

function toPdfSafeText(value) {
  return cellText(value)
    .replaceAll('\t', ' ')
    .replace(/[^\x20-\x7E]/g, '-')
}
