import { useEffect, useState } from 'react'

type ApiStatus = 'loading' | 'ok' | 'error'

function App() {
  const [apiStatus, setApiStatus] = useState<ApiStatus>('loading')

  // Runs once after the first render, like onMounted in Vue.
  // The empty dependency array means "never re-run".
  useEffect(() => {
    fetch('/api/health')
      .then((res) => setApiStatus(res.ok ? 'ok' : 'error'))
      .catch(() => setApiStatus('error'))
  }, [])

  return (
    <main>
      <h1>CRM Rodium</h1>
      <p>API: {apiStatus}</p>
    </main>
  )
}

export default App