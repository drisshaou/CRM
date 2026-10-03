import type { CellValue, ColumnType } from './api'

// Dates are stored as YYYY-MM-DD: read and display them in UTC so the
// browser time zone never shifts the day
const dateFormat = new Intl.DateTimeFormat('fr-FR', { timeZone: 'UTC' })
const numberFormat = new Intl.NumberFormat('fr-FR')

// +33612345678 -> 06 12 34 56 78 (anything else is shown as stored)
function formatPhone(value: string): string {
  const match = /^\+33(\d{9})$/.exec(value)
  if (!match) {
    return value
  }
  return `0${match[1]}`.replace(/(\d{2})(?=\d)/g, '$1 ')
}

function formatDate(value: string): string {
  const date = new Date(`${value}T00:00:00Z`)
  return Number.isNaN(date.getTime()) ? value : dateFormat.format(date)
}

export function formatCell(
  value: CellValue | undefined,
  type: ColumnType,
): string {
  if (value === null || value === undefined || value === '') {
    return ''
  }
  switch (type) {
    case 'number':
      return typeof value === 'number' ? numberFormat.format(value) : String(value)
    case 'date':
      return formatDate(String(value))
    case 'phone':
      return formatPhone(String(value))
    case 'text':
      return String(value)
  }
}