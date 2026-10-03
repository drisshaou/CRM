import type { Column, ColumnType, Filter, FilterOp, Sort } from './api'

// What the user typed in one filter cell (always a string, like any input)
export type FilterDraft = { op: FilterOp; value: string }

// Operators offered for each column type (the API checks them again)
export const OPERATORS: Record<ColumnType, { op: FilterOp; label: string }[]> = {
  text: [
    { op: 'contains', label: 'contient' },
    { op: 'equals', label: 'égal à' },
  ],
  number: [
    { op: 'eq', label: '=' },
    { op: 'gt', label: '>' },
    { op: 'lt', label: '<' },
  ],
  date: [
    { op: 'eq', label: 'le' },
    { op: 'gt', label: 'après' },
    { op: 'lt', label: 'avant' },
  ],
  phone: [{ op: 'contains', label: 'contient' }],
}

export function defaultDraft(type: ColumnType): FilterDraft {
  return { op: OPERATORS[type][0].op, value: '' }
}

// Turns the filter cells into API filters, skipping empty or unusable ones
export function toFilters(
  drafts: Record<string, FilterDraft>,
  columns: Column[],
): Filter[] {
  const filters: Filter[] = []
  for (const column of columns) {
    const draft = drafts[column.id]
    const text = draft?.value.trim() ?? ''
    if (!draft || text === '') {
      continue
    }
    if (column.type === 'number') {
      const number = Number(text)
      if (Number.isFinite(number)) {
        filters.push({ columnId: column.id, op: draft.op, value: number })
      }
    } else {
      filters.push({ columnId: column.id, op: draft.op, value: text })
    }
  }
  return filters
}

// Header click cycle: ascending -> descending -> no sort
export function nextSort(current: Sort | null, columnId: string): Sort | null {
  if (current?.columnId !== columnId) {
    return { columnId, dir: 'asc' }
  }
  return current.dir === 'asc' ? { columnId, dir: 'desc' } : null
}