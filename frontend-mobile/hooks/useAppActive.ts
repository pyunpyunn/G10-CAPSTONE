import { useEffect, useState } from 'react';
import { AppState } from 'react-native';

export function useAppActive() {
  const [active, setActive] = useState(AppState.currentState !== 'background' && AppState.currentState !== 'inactive');
  useEffect(() => {
    const subscription = AppState.addEventListener('change', state => setActive(state === 'active'));
    // AppState may resolve between the initial render and subscription.
    setActive(AppState.currentState !== 'background' && AppState.currentState !== 'inactive');
    return () => subscription.remove();
  }, []);
  return active;
}
