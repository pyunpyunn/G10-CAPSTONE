# 18 - System Architecture

```mermaid
flowchart TD
  subgraph Client["Client applications"]
    Web["HQ/Admin web app\nReact + Vite"]
    Household["Household mobile app\nExpo React Native"]
    Rescuer["Rescuer mobile app\nExpo React Native"]
  end

  subgraph API["Backend API layer"]
    Laravel["Laravel API"]
    Routes["routes/api.php"]
    Controllers["Thin API controllers"]
    Services["Service classes\nbusiness logic"]
    Validation["Request validation"]
    Sanctum["Laravel Sanctum tokens"]
  end

  subgraph Data["Data layer"]
    DB["MySQL active connection\nlocal or shared from .env"]
    Storage["File/audio storage\nradio clips and exports"]
    Cache["Laravel cache/jobs"]
  end

  subgraph External["External systems and sources"]
    SafeTrack["SafeTrack\nhousehold accounts"]
    EvaTrack["EvaTrack\nrequests"]
    TrackingAid["TrackingAid/MappingAid\nfuture validated handoff"]
    Weather["Open-Meteo + PAGASA links\nweather source"]
    MapTiles["Map tiles/routing source"]
  end

  Web --> Laravel
  Household --> Laravel
  Rescuer --> Laravel
  Laravel --> Routes --> Controllers --> Validation --> Services
  Services --> Sanctum
  Services --> DB
  Services --> Storage
  Services --> Cache
  Services <--> SafeTrack
  Services <--> EvaTrack
  Services --> TrackingAid
  Services --> Weather
  Services --> MapTiles

  DB --> Web
  DB --> Household
  DB --> Rescuer
```

## Architecture rules

- Only `backend-laravel/.env` should change when switching database connections.
- Web and mobile clients must call the API, not the database directly.
- Controllers should stay small and delegate logic to services.
- Pages should display active database records only.

