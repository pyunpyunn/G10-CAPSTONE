import {
  Activity,
  CloudRain,
  CloudSun,
  Navigation,
  Thermometer,
} from 'lucide-react'

export function makeMonitorRows(latest) {
  return [
    {
      title: 'Live condition updates',
      sub: 'Auto refresh through Laravel Scheduler every 3 hours.',
      value: latest ? 'Saved' : 'No data',
      tone: '',
      icon: Activity,
    },
    {
      title: 'Temperature monitoring',
      sub: 'Air temperature and feels-like variance.',
      value: latest ? valueWithUnit(latest.temperature, '°C') : '-',
      tone: 'warning',
      icon: Thermometer,
    },
    {
      title: 'Rainfall monitoring',
      sub: 'Current precipitation and 3-day outlook.',
      value: latest ? valueWithUnit(latest.rainfall_mm, 'mm') : '-',
      tone: riskToneFor(latest?.risk_level),
      icon: CloudRain,
    },
    {
      title: 'Wind speed and direction monitoring',
      sub: 'Bearing, speed, and gust context.',
      value: latest ? `${valueWithUnit(latest.wind_speed, 'km/h')} ${latest.wind_direction || ''}` : '-',
      tone: riskToneFor(latest?.risk_level),
      icon: Navigation,
    },
    {
      title: 'Weather condition display',
      sub: 'Sunny, rainy, stormy, cloudy, storm.',
      value: latest?.condition_name || '-',
      tone: '',
      icon: CloudSun,
    },
  ]
}

export function valueWithUnit(value, unit) {
  if (value === null || value === undefined || value === '') {
    return '-'
  }

  return `${Number(value).toFixed(Number(value) % 1 === 0 ? 0 : 1)}${unit}`
}

export function rainDetail(latest) {
  if (!latest) {
    return 'Rainfall appears after refresh.'
  }

  const forecast = latest.daily_forecast?.[0]

  return forecast
    ? `${forecast.rain_probability}% max rain chance today, ${forecast.rainfall_sum} mm forecast sum.`
    : 'Current precipitation from the latest saved snapshot.'
}

export function windDetail(latest) {
  if (!latest) {
    return 'Wind speed and direction appear after refresh.'
  }

  return `From ${latest.wind_direction || 'unknown direction'}, gusts up to ${latest.wind_gusts || '-'} km/h.`
}

export function meter(value, max) {
  if (!value) {
    return 0
  }

  return Math.min(100, Math.max(0, (Number(value) / max) * 100))
}

export function riskToneFor(level) {
  if (level === 'critical') {
    return 'critical'
  }

  if (level === 'warning') {
    return 'warning'
  }

  return 'normal'
}

export function historyTone(level) {
  if (level === 'critical') {
    return 'red'
  }

  if (level === 'warning') {
    return 'amber'
  }

  return 'green'
}

export function apiErrorMessage(error, fallback) {
  if (!error?.response) {
    return 'Cannot connect to the server right now. Please check the backend connection.'
  }

  return error.response.data?.message || fallback
}
