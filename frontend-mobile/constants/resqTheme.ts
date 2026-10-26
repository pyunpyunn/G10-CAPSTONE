export const palette = {
  nav: '#0f1c2d',
  navActive: '#1f3e5a',
  navHover: '#2a4f72',
  navText: '#e8eef5',
  navMuted: '#a0b4c8',
  brandRed: '#c72e32',
  page: '#f2f4f7',
  card: '#ffffff',
  secondary: '#edf1f6',
  border: '#d0d9e4',
  borderStrong: '#b0bfce',
  text: '#0a1520',
  textSoft: '#3a4d61',
  safe: '#3a7d57',
  evacuated: '#2a5a90',
  unsafe: '#962020',
  unchecked: '#6a7f94',
  warning: '#8f5c18',
  dispatch: '#240e6a',
};

export const typography = {
  families: {
    regular: '400' as const,
    medium: '500' as const,
    semibold: '600' as const,
    bold: '700' as const,
    extraBold: '800' as const,
  },
  sizes: {
    caption: 12,
    small: 12,
    body: 14,
    subtitle: 16,
    heading: 20,
    display: 24,
  },
  weights: {
    regular: '400' as const,
    medium: '500' as const,
    semibold: '600' as const,
    bold: '700' as const,
  },
};

export const spacing = {
  xs: 6,
  sm: 10,
  md: 14,
  lg: 18,
  xl: 24,
};

export const radius = {
  sm: 8,
  md: 10,
  lg: 14,
  pill: 999,
};

export const shadow = {
  shadowColor: '#0a1520',
  shadowOffset: { width: 0, height: 8 },
  shadowOpacity: 0.08,
  shadowRadius: 16,
  elevation: 4,
};
