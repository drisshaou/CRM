import { useEffect, useRef } from 'react'
import { useInfiniteQuery, useQuery } from '@tanstack/react-query'
import { fetchColumns, fetchContactsPage } from './api'
import { formatCell } from './format'
import styles from './Grid.module.css'

function Grid() {
  // Like useFetch in Nuxt: data, loading and error states, cached by key
  const columnsQuery = useQuery({
    queryKey: ['columns'],
    queryFn: fetchColumns,
  })

  // A list of pages; the API's nextOffset tells where the next page starts
  const contactsQuery = useInfiniteQuery({
    queryKey: ['contacts'],
    queryFn: ({ pageParam }) => fetchContactsPage(pageParam),
    initialPageParam: 0,
    getNextPageParam: (lastPage) => lastPage.nextOffset ?? undefined,
  })

  const { hasNextPage, isFetchingNextPage, fetchNextPage } = contactsQuery
  const ready = columnsQuery.isSuccess && contactsQuery.isSuccess

  // Template ref on the last (invisible) row of the table
  const sentinelRef = useRef<HTMLTableRowElement>(null)

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
    <div className={styles.wrapper}>
      <table className={styles.table}>
        <thead>
          <tr>
            {columns.map((column) => (
              <th key={column.id}>{column.name}</th>
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