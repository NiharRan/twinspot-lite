# Connection Map Districts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Connection map shows each hub as a district with its orders spread around it, clear gaps between districts, bridge orders between districts.

**Architecture:** Replace the single force simulation in `layout()` with: (1) classify orders as district members or bridges, (2) force sim over hubs only to place district centers, (3) place members on circles around their hub, bridges at the mean of their hubs. Pure positioning code lives in a new `src/lib/districts.ts`; `connection-map.tsx` only turns positions into nodes.

**Tech Stack:** React, `@xyflow/react`, `d3-force` (already installed), TypeScript.

**Spec:** `docs/superpowers/specs/2026-11-14-connection-map-districts-design.md`

## Global Constraints

- No backend or REST change. Input is `RingDetail` (`orders`, `hubs`, `edges: [number, string][]`) from `src/types.ts`.
- No new dependency. Keep `d3-force` and `@xyflow/react`.
- Layout computed up front (`.stop().tick(n)`), graph must not jiggle on open.
- Never hide an order or hub.
- Orders with 2+ hubs are bridges. Over 12 orders in a district opens a second ring.
- Orders spread evenly (readable), not packed tight.
- Keep existing edge colors; district edges lighter, bridge edges bolder.

## Review Focus

- Ring with one hub and 0 bridges: one district, no NaN positions.
- Ring with 50+ orders on one hub: second ring, no overlapping nodes.
- Order whose hub ids are missing from `ring.hubs`: skipped from the hub mean, still placed (at origin), never crashes.
- Two hubs with no shared bridge order (disconnected): districts still separated by the gap.
- Bridge linked to 3+ hubs: sits at the mean of all of them.

---

### Task 1: Pure district positions

**Files:**
- Create: `src/lib/districts.ts`

**Interfaces:**
- Consumes: `RingDetail` from `src/types.ts`.
- Produces: `districtPositions( ring: RingDetail ): Map< string, { x: number; y: number } >`. Keys: hub id (`h.id`) and `o${ edge_id }` for orders, matching existing node ids.

- [ ] **Step 1: Implement `districtPositions( ring: RingDetail ): Map< string, { x: number; y: number } >`**
  - Constants at top: `RING_CAP = 12`, `NODE_GAP = 70` (spacing along a ring), `RING_STEP = 80` (radius step between rings), `DISTRICT_GAP = 140`.
  - Hubs per order from `ring.edges`. Order with exactly 1 hub is a member of it; 2+ is a bridge.
  - District radius = outer ring radius of its members (ring `k` holds `RING_CAP * (k + 1)` orders, radius `RING_STEP * (k + 1)`; first ring radius at least `RING_STEP`), or `RING_STEP` for an empty district.
  - Centers: `forceSimulation` over hubs only, link between two hubs per shared bridge order, `forceManyBody` strong negative, `forceCollide( districtRadius + DISTRICT_GAP / 2 )`, `.stop().tick( 300 )`.
  - Members: evenly spaced angles on their ring around the hub center.
  - Bridges: mean of their known hub centers, plus a small perpendicular offset (about 30px) per bridge sharing the same hub set so they do not stack. No known hubs: `{ x: 0, y: 0 }`.

- [ ] **Step 2: Typecheck**

Run: `npm run typecheck`
Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add src/lib/districts.ts
git commit -m "feat: district positions for the connection map"
```

### Task 2: Use districts in the map and tune edges

**Files:**
- Modify: `src/screens/connection-map.tsx` (`layout()` at lines ~44-64, `edges` memo at ~70-83)

**Interfaces:**
- Consumes: `districtPositions( ring )` from Task 1.

- [ ] **Step 1: Rewrite `layout( ring: RingDetail ): Node[]`** to call `districtPositions` and map `ring.orders` / `ring.hubs` to nodes with the same ids, types and `data` as today. Remove the `d3-force` imports from this file.

- [ ] **Step 2: Edge style.** In the `edges` memo, edges to a hub from a member order (order has exactly 1 hub) get `strokeWidth: 1`, opacity 0.5; bridge edges keep today's width and colors. Dashed and top-hub styles unchanged.

- [ ] **Step 3: Raise visibility.** Lower `minZoom` stays `0.3`; keep `fitView` padding `0.2`.

- [ ] **Step 4: Verify**

Run: `npm run typecheck && npm run build`
Expected: both pass.
Then open Accounts > a ring with many orders in WP admin (browser): districts clearly separated, no overlapping nodes, bridges between districts, fullscreen and refresh still work. Check a small ring (2-3 orders) too.

- [ ] **Step 5: Commit**

```bash
git add src/screens/connection-map.tsx
git commit -m "feat: show connection map as districts"
```
