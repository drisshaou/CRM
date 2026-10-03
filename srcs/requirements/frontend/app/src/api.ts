export type SortDir = 'asc' | 'desc'

export type Sort = { columnId: string; dir: SortDir }

export type FilterOp = 'contains' | 'equals' | 'eq' | 'gt' | 'lt'

export type Filter = { columnId: string; op: FilterOp; value: string | number }

// Shapes returned by the NestJS API
export type ColumnType = 'text' | 'number' | 'date' | 'phone'

export type Column = {
  id: string
  name: string
  type: ColumnType
  position: number
}

export type CellValue = string | number | null

// values is keyed by column id: { "<columnId>": value }
export type Contact = {
  id: number
  values: Record<string, CellValue>
}

export type ContactPage = {
  items: Contact[]
  nextOffset: number | null
}

const PAGE_SIZE = 50

// Relative URLs: the same code works behind the Vite proxy (dev) and nginx (prod)
async function getJson<T>(url: string): Promise<T> {
  const response = await fetch(url)
  if (!response.ok) {
    throw new Error(`${url} failed with HTTP ${response.status}`)
  }
  return (await response.json()) as T
}

export function fetchColumns(): Promise<Column[]> {
  return getJson<Column[]>('/api/columns')
}

export function fetchContactsPage(
  offset: number,
  sort: Sort | null,
  filters: Filter[],
): Promise<ContactPage> {
  const params = new URLSearchParams({
    limit: String(PAGE_SIZE),
    offset: String(offset),
  })
  if (sort) {
    params.set('sort', sort.columnId)
    params.set('dir', sort.dir)
  }
  if (filters.length > 0) {
    params.set('filters', JSON.stringify(filters))
  }
  return getJson<ContactPage>(`/api/contacts?${params.toString()}`)
}