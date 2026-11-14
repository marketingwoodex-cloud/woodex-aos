import { useCallback, useEffect, useRef, useState } from 'react';
import { api } from './api.js';

/** Debounces a fast-changing value (search boxes). */
export function useDebounced(value, delay = 300) {
  const [debounced, setDebounced] = useState(value);

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delay);
    return () => clearTimeout(timer);
  }, [value, delay]);

  return debounced;
}

/**
 * Fetches `path` and re-runs whenever it changes.
 * Pass null to skip fetching.
 */
export function useFetch(path) {
  const [state, setState] = useState({ data: null, loading: Boolean(path), error: null });
  const requestId = useRef(0);

  const load = useCallback(
    async (showSpinner = true) => {
      if (!path) return;
      const id = ++requestId.current;
      if (showSpinner) setState((prev) => ({ ...prev, loading: true, error: null }));
      try {
        const payload = await api(path);
        if (id === requestId.current) setState({ data: payload, loading: false, error: null });
      } catch (error) {
        if (id === requestId.current) setState({ data: null, loading: false, error: error.message });
      }
    },
    [path],
  );

  useEffect(() => {
    setState({ data: null, loading: Boolean(path), error: null });
    load();
  }, [load, path]);

  return { ...state, reload: () => load(false), refresh: () => load(true) };
}
