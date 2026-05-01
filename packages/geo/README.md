# waaseyaa/geo

**Layer 0 — Foundation**

Geospatial utilities for Waaseyaa: distance calculations, coordinate helpers.

`GeoDistance` ships haversine and equirectangular approximations for lat/lon pairs in metres or kilometres, plus a small set of coordinate parsers for common formats. Pure utility — no storage, no service, no global state.

Key classes: `GeoDistance`, `GeoServiceProvider`.
