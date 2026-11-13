# Connection map: districts layout

## Intent
Big rings make the connection map (`src/screens/connection-map.tsx`) a hairball: force layout overlaps nodes, fit-to-view shrinks everything. Goal: show the graph neatly, like buildings grouped into city districts, with clear gaps.

## Goals
- Each hub (shared email/phone/address) is a district center. Its orders sit around it in a tidy ring.
- Clear gap between districts and between nodes. No overlapping nodes.
- Orders linked to 2+ hubs ("bridges") sit between their districts.
- Readable at about 60 nodes without zooming.

## Non-goals
- No backend or REST change. `RingDetail` (`orders`, `hubs`, `edges`) is enough.
- No collapse, filter, or focus features (separate ideas, not now).
- No new dependency. Keep `d3-force` and `@xyflow/react`.

## Anti-goals
- Graph must not jiggle on open (keep layout computed up front).
- Must not hide orders or hubs.

## Approaches considered
- A. Loosen force params only: tiny, still a hairball at scale.
- B. Districts: chosen. Structure matches the "city" request and reuses existing data.
- C. Radial diagram: clean but crowds with many hubs and does not look like a city.

## Design (my calls)
Replace `layout()` only:
1. Orders with exactly one hub belong to that hub's district. Orders with 2+ hubs are bridges.
2. Place district centers with a force sim over hubs only (links between hubs that share a bridge order, large distance, strong repulsion, collide radius = district radius + gap).
3. District radius grows with its order count. Its orders are spread evenly on a circle (a second inner/outer ring when over about 12), so each stays readable (my call: evenly spread, not dense).
4. Bridge orders sit at the average position of their hubs, nudged off the line to avoid sitting on a hub.
5. Edge styling: keep current colors. Lighter inside a district, bold for bridges (small tweak in the `edges` memo).
6. Rest of the component (fullscreen, refresh, fit view) unchanged.

## Left to the builder
Exact radii, gap sizes, and ring capacity numbers. Tune by eye on a ring of about 50 orders.
