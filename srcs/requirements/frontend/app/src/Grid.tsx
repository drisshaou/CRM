import { useEffect, useMemo, useRef, useState } from 'react'
import {
  keepPreviousData,
  useInfiniteQuery,
  useQuery,
} from '@tanstack/react-query'
import { fetchColumns, fetchContactsPage, type Sort } from './api'
import ColumnFilter from './ColumnFilter'
import { defaultDraft, nextSort, toFilters, type FilterDraft } from './filters'
import { formatCell } from './format'
import { useDebouncedValue } from './useDebouncedValue'
import styles from './Grid.module.css'

function Grid() {
  const [sort, setSort] = useState<Sort | null>(null)
  // Filter cells as typed, keyed by column id
  const [drafts, setDrafts] = useState<Record<string, FilterDraft>>({})
  const debouncedDrafts = useDebouncedValue(drafts, 300)

  // Like useFetch in Nuxt: data, loading and error states, cached by key
  const columnsQuery = useQuery({
    queryKey: ['columns'],
    queryFn: fetchColumns,
  })

  // useMemo keeps the same array between renders while nothing changed;
  // without it, the scroll reset effect below would run on every render
  const filters = useMemo(
    () => toFilters(debouncedDrafts, columnsQuery.data ?? []),
    [debouncedDrafts, columnsQuery.data],
  )

  // sort and filters are part of the key: changing them starts a new list
  // from page 0, which resets the infinite scroll
  const contactsQuery = useInfiniteQuery({
    queryKey: ['contacts', { sort, filters }],
    queryFn: ({ pageParam }) => fetchContactsPage(pageParam, sort, filters),
    initialPageParam: 0,
    getNextPageParam: (lastPage) => lastPage.nextOffset ?? undefined,
    // Keep showing the previous rows while the new list loads, so the
    // table (and the focused filter input) never unmounts
    placeholderData: keepPreviousData,
  })

  const { hasNextPage, isFetchingNextPage, fetchNextPage } = contactsQuery
  const ready = columnsQuery.isSuccess && contactsQuery.isSuccess

  const wrapperRef = useRef<HTMLDivElement>(null)
  // Template ref on the last (invisible) row of the table
  const sentinelRef = useRef<HTMLTableRowElement>(null)

  // A new sort or filter shows a new list: go back to the top
  useEffect(() => {
    wrapperRef.current?.scrollTo({ top: 0 })
  }, [sort, filters])

  // Infinite scroll: load the next page when the last row becomes visible.
  // Re-runs when one of the listed values changes; the cleanup disconnects
  // the previous observer (StrictMode runs effects twice in dev).
  useEffect(() => {
    const sentinel = sentinelRef.current
    if (!ready || !sentinel || !hasNextPage) {
      return
    }
    const observer = new IntersectionObserver((entries) => {
      if (entries.some((entry) => entry.isIntersecting) && !isFetchingNextPage) {
        void fetchNextPage()
      }
    })
    observer.observe(sentinel)
    return () => observer.disconnect()
  }, [ready, hasNextPage, isFetchingNextPage, fetchNextPage])

  if (columnsQuery.isPending || contactsQuery.isPending) {
    return <p>Chargement…</p>
  }
  if (columnsQuery.isError || contactsQuery.isError) {
    return <p className={styles.error}>Impossible de charger les données.</p>
  }

  const columns = columnsQuery.data
  const contacts = contactsQuery.data.pages.flatMap((page) => page.items)

  return (
    <div ref={wrapperRef} className={styles.wrapper}>
      <table
        className={`${styles.table} ${contactsQuery.isPlaceholderData ? styles.stale : ''}`}
      >
        <thead>
          <tr>
            {columns.map((column) => {
              const dir = sort?.columnId === column.id ? sort.dir : null
              return (
                <th
                  key={column.id}
                  aria-sort={
                    dir === 'asc' ? 'ascending' : dir === 'desc' ? 'descending' : 'none'
                  }
                >
                  <button
                    type="button"
                    className={styles.sortButton}
                    onClick={() => setSort((current) => nextSort(current, column.id))}
                  >
                    {column.name}
                    <span className={styles.sortIcon}>
                      {dir === 'asc' ? '▲' : dir === 'desc' ? '▼' : ''}
                    </span>
                  </button>
                </th>
              )
            })}
          </tr>
          <tr>
            {columns.map((column) => (
              <th key={column.id}>
                <ColumnFilter
                  column={column}
                  draft={drafts[column.id] ?? defaultDraft(column.type)}
                  onChange={(draft) =>
                    setDrafts((current) => ({ ...current, [column.id]: draft }))
                  }
                />
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {contacts.map((contact) => (
            <tr key={contact.id}>
              {columns.map((column) => (
                <td
                  key={column.id}
                  className={column.type === 'number' ? styles.number : undefined}
                >
                  {formatCell(contact.values[column.id], column.type)}
                </td>
              ))}
            </tr>
          ))}
          <tr ref={sentinelRef}>
            <td colSpan={columns.length} className={styles.status}>
              {isFetchingNextPage
                ? 'Chargement…'
                : hasNextPage
                  ? ''
                  : `${contacts.length} contacts`}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  )
}

export default Grid