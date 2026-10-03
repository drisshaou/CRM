import { useState, type KeyboardEvent } from 'react'
import type { CellValue, ColumnType } from './api'
import { formatCell } from './format'
import styles from './Grid.module.css'

const INPUT_TYPES: Record<ColumnType, string> = {
  text: 'text',
  number: 'number',
  date: 'date',
  phone: 'tel',
}

// Value shown in the input while editing (dates stay YYYY-MM-DD for type="date")
function toInputValue(value: CellValue | undefined, type: ColumnType): string {
  if (value === null || value === undefined) {
    return ''
  }
  return type === 'phone' ? formatCell(value, type) : String(value)
}

// Value sent to the API; an empty input clears the cell
function toCellValue(input: string, type: ColumnType): CellValue {
  if (input.trim() === '') {
    return null
  }
  return type === 'number' ? Number(input) : input
}

type Props = {
  value: CellValue | undefined
  type: ColumnType
  onSave: (value: CellValue) => Promise<unknown>
}

// Double-click to edit; Enter or leaving the cell saves, Escape cancels
function EditableCell({ value, type, onSave }: Props) {
  const [draft, setDraft] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  // Enter then blur would otherwise send the same change twice
  const [saving, setSaving] = useState(false)

  const save = async () => {
    if (draft === null || saving) {
      return
    }
    const next = toCellValue(draft, type)
    if (next === (value ?? null)) {
      setDraft(null)
      return
    }
    setSaving(true)
    try {
      await onSave(next)
      setDraft(null)
      setError(null)
    } catch (caught) {
      // Keep the input open with the API message (e.g. invalid phone)
      setError(caught instanceof Error ? caught.message : 'Erreur')
    } finally {
      setSaving(false)
    }
  }

  const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'Enter') {
      void save()
    } else if (event.key === 'Escape') {
      setDraft(null)
      setError(null)
    }
  }

  const className = [
    type === 'number' ? styles.number : '',
    error ? styles.invalid : '',
  ].join(' ')

  if (draft !== null) {
    return (
      <td className={className} title={error ?? undefined}>
        <input
          className={styles.cellInput}
          type={INPUT_TYPES[type]}
          value={draft}
          autoFocus
          onChange={(event) => setDraft(event.target.value)}
          onKeyDown={onKeyDown}
          onBlur={() => void save()}
        />
      </td>
    )
  }

  return (
    <td
      className={className}
      onDoubleClick={() => setDraft(toInputValue(value, type))}
    >
      {formatCell(value, type)}
    </td>
  )
}

export default EditableCell