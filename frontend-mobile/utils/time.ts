const philippineDateTimeFormatter = new Intl.DateTimeFormat('en-PH', {
  timeZone: 'Asia/Manila',
  month: 'short',
  day: '2-digit',
  hour: 'numeric',
  minute: '2-digit',
});

const philippineTimeFormatter = new Intl.DateTimeFormat('en-PH', {
  timeZone: 'Asia/Manila',
  hour: 'numeric',
  minute: '2-digit',
});

export function formatPhilippineDateTime(value?: string, fallback = 'Not recorded') {
  if (!value) return fallback;

  const date = parseAsPhilippineTime(value);

  if (!date) {
    return fallback;
  }

  return philippineDateTimeFormatter.format(date);
}

export function formatPhilippineTime(value: Date = new Date()) {
  return philippineTimeFormatter.format(value);
}

function parseAsPhilippineTime(value: string) {
  const hasTimezone = /(?:z|[+-]\d{2}:?\d{2})$/i.test(value);
  const normalized = value.trim().replace(' ', 'T');
  const date = new Date(hasTimezone ? normalized : `${normalized}+08:00`);

  return Number.isNaN(date.getTime()) ? null : date;
}
