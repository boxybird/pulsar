// Current Datastar does not have set header globally
// for all actions. I'm adding a WP nonce to request
// if the fetch originator is Datastar.
let originalFetch = window.fetch

window.fetch = function () {
  if (!arguments[1].headers || arguments[1].headers['Datastar-Request'] !== true) {
    return originalFetch.apply(this, arguments)
  }

  arguments[1] = {
    ...arguments[1], headers: {
      ...arguments[1]?.headers,
      'Pulse-Nonce': pulsarData.nonce, // pulsarData uses wp_localize_script()
    },
  }

  return originalFetch.apply(this, arguments)
}
