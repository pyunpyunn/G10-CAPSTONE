# RESQPERATION PAGASA Ten-Day Forecast API Request Letter

Use this as the formal email/request draft for requesting access to the PAGASA Ten-Day Forecast API.

## Email Subject

```text
Request for PAGASA Ten-Day Forecast API Token for Academic Capstone Project
```

## Email Recipient

```text
asolis@pagasa.dost.gov.ph
```

Optional CC:

```text
information@pagasa.dost.gov.ph
```

## Formal Email Draft

```text
Good day, Ms. Ana Liza S. Solis and DOST-PAGASA CLIMPS/CAD Team,

We are Grade 10 students currently developing an academic capstone project titled RESQPERATION: Barangay Disaster Response and Rescue Coordination System.

RESQPERATION is designed to help barangay HQ/Admin monitor household safety status, rescue dispatch, disaster broadcasting, weather updates, and mapping during disaster preparedness and response operations. As part of our Weather Updates module, we would like to request official access to the PAGASA Ten-Day Weather Forecast API.

We respectfully request an API token that may be used only for academic and capstone development purposes. The system will use the forecast data to display official weather forecast information to the HQ/Admin dashboard, support disaster preparedness monitoring, and help avoid relying on unverified or manually encoded weather data.

Project details:

Project title: RESQPERATION: Barangay Disaster Response and Rescue Coordination System
School: [Insert school name]
Grade/Section: [Insert grade and section]
Adviser/Teacher: [Insert adviser or teacher name]
Team members:
- Barro, Nina Kathleen
- Eria, Ereca Joy
- Arellano, Vinzon
- Pacillan, Vince

Requested API use:
- Retrieve ten-day weather forecast data for the target city/municipality/barangay area.
- Display the latest forecast in the HQ/Admin web dashboard.
- Store forecast snapshots in our local project database for event monitoring and situation-report reference.
- Clearly label PAGASA as the official source of the forecast data.

We would also like to ask for the following details if available:

1. API token issuance process and requirements
2. Approved endpoint access for the Ten-Day Forecast API
3. Rate limit and allowed request frequency
4. Required citation/source label for displaying PAGASA forecast data
5. Any academic-use restrictions we must follow

We understand that the API token must be kept secure and will not be exposed in our frontend code. The token will be stored only in the backend environment file and used through our Laravel backend server.

Thank you very much for your time and assistance. We hope to use official PAGASA forecast data responsibly in our disaster-response capstone project.

Respectfully,

[Sender full name]
RESQPERATION Capstone Team
[School name]
[Contact number]
[Email address]
```

## Details To Prepare Before Sending

- School name
- Adviser or teacher name
- Grade and section
- Sender email and contact number
- Target location for forecast data, such as city/municipality/province
- Short project endorsement from adviser, if available

## Requested API Endpoints

Based on the Ten-Day Forecast API documentation, these are the likely needed endpoints:

```text
https://tenday.pagasa.dost.gov.ph/api/v1/tenday/current
https://tenday.pagasa.dost.gov.ph/api/v1/tenday/full
https://tenday.pagasa.dost.gov.ph/api/v1/location
https://tenday.pagasa.dost.gov.ph/api/v1/validate
```

## Backend Integration Note

Do not place the PAGASA token in React or mobile frontend code.

Store it in Laravel `.env` only:

```text
PAGASA_TENDAY_TOKEN=
PAGASA_TENDAY_BASE_URL=https://tenday.pagasa.dost.gov.ph/api/v1
PAGASA_TENDAY_LOCATION=
```

Laravel should call PAGASA through the backend:

```php
$response = Http::withHeaders([
    'token' => env('PAGASA_TENDAY_TOKEN'),
])->get(env('PAGASA_TENDAY_BASE_URL').'/tenday/full', [
    'municity' => env('PAGASA_TENDAY_LOCATION'),
    'page' => 'none',
]);
```

## Recommended RESQPERATION Weather Source Rule

- PAGASA Ten-Day Forecast API: official forecast reference when token access is approved.
- Open-Meteo: current structured numeric forecast source while waiting for PAGASA token approval.
- PAGASA website links: official advisory confirmation for tropical cyclone, rainfall, thunderstorm, and flood warnings.

The system must clearly label the data source shown on the Weather Updates page.

## Current Implementation While Waiting For Token

RESQPERATION currently uses Laravel to fetch Open-Meteo forecast snapshots automatically every 3 hours and save them in `weather_logs`.

The Weather Updates page displays:

- latest saved Open-Meteo numeric forecast snapshot
- 3-day forecast outlook
- PAGASA official advisory links
- the required note: "Confirm official warnings through PAGASA before broadcasting."

When PAGASA approves the Ten-Day API token, add the token to Laravel `.env` and integrate the PAGASA source in the backend. Do not expose the token in React or mobile code.
