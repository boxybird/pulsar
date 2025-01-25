// Current Datastar does not have a way to set headers
// globally for all actions. I'm adding a WP nonce
// to request if the fetch originator is Datastar.
(function () {
  const originalFetch = window.fetch

  window.fetch = function (url, options = {}) {
    let isDatastarRequest = !!options.headers['Datastar-Request']

    if (!isDatastarRequest) {
      return originalFetch(url, options)
    }

    const headers = {
      'Pulse-Nonce': pulsarData.nonce, // pulsarData uses wp_localize_script()
    }

    options.headers = { ...headers, ...options.headers }
    return originalFetch(url, options)
  }
})()
