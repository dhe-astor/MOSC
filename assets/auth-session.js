(() => {
  let activeToken = window.localStorage.getItem('msoc_token');

  for (const method of ['pushState', 'replaceState']) {
    const navigate = window.history[method].bind(window.history);

    window.history[method] = (state, title, url) => {
      const nextToken = window.localStorage.getItem('msoc_token');

      if (url != null && nextToken !== activeToken) {
        activeToken = nextToken;
        window.location.assign(new URL(url, window.location.href).href);
        return;
      }

      return navigate(state, title, url);
    };
  }
})();
