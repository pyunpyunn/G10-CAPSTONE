export default {
  getItemSync: (key: string) => typeof localStorage === 'undefined' ? null : localStorage.getItem(key),
  setItemSync: (key: string, value: string) => localStorage.setItem(key, value),
  removeItemSync: (key: string) => localStorage.removeItem(key),
};
