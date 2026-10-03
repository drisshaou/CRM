import type { Column, ColumnType } from './api'
import { OPERATORS, type FilterDraft } from './filters'
import styles from './Grid.module.css'

// Native input types give the right keyboard and value format for free
// (type="date" always yields YYYY-MM-DD, the format the API expects)
const INPUT_TYPES: Record<ColumnType, string> = {
  text: 'text',
  number: 'number',
  date: 'date',
  phone: 'tel',
}

type Props = {
  column: Column
  draft: FilterDraft
  onChange: (draft: FilterDraft) => void
}

// Controlled inputs: the value lives in the parent's state (like v-model)
function ColumnFilter({ column, draft, onChange }: Props) {
  const operators = OPERATORS[column.type]

  return (
    <div className={styles.filter}>
      {operators.length > 1 && (
        <select
          aria-label={`Opérateur pour ${column.name}`}
          value={draft.op}
          onChange={(event) => {
            const op = operators.find((o) => o.op === event.target.value)?.op
            onChange({ ...draft, op: op ?? draft.op })
          }}
        >
          {operators.map((operator) => (
            <option key={operator.op} value={operator.op}>
              {operator.label}
            </option>
          ))}
        </select>
      )}
      <input
        aria-label={`Filtrer ${column.name}`}
        type={INPUT_TYPES[column.type]}
        placeholder="Filtrer"
        value={draft.value}
        onChange={(event) => onChange({ ...draft, value: event.target.value })}
      />
    </div>
  )
}

export default ColumnFilter