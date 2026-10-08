# RESQPERATION Frontend Alignment Audit Checklist

**Date:** June 23, 2026  
**Scope:** frontend-web UI components, cards, text, sections, modals, and overlays  
**Objective:** Identify misaligned elements, overlapping cards, and text positioning issues

---

## Part 1: Identified Alignment Issues

### 1. Landing Hero Visual Panels (Absolute Positioning Overlap)
**Location:** `frontend-web/src/App.css` (lines 460–500) | `frontend-web/src/components/login/LoginLanding.jsx`  
**Issue:** `.visual-panel` elements (status-panel, dispatch-panel) are absolutely positioned inside `.hero-visual`.  
**Risk:** On narrow/medium screens, panels can overlap hero text or extend outside viewport.  
**Components Affected:**
- Status panel (left: 28px, bottom: 28px, width: 252px)
- Dispatch panel (right: 28px, top: 34px, width: 230px)

**Expected Fix:** Add media queries or use `max-width` constraints to reposition on screens < 1024px.

---

### 2. Summary Card Strips (4-Column Grid Breakage)
**Location:** `frontend-web/src/App.css` (lines 4841–4876)  
**Components:** 
- `.notification-summary-strip` 
- `.profile-summary-strip`
- Used by: [NotificationSummary.jsx](frontend-web/src/components/notifications/NotificationSummary.jsx) | [ProfileSummary.jsx](frontend-web/src/components/profile/ProfileSummary.jsx)

**Issue:** `grid-template-columns: repeat(4, minmax(0, 1fr))` forces 4 columns even on tablets/mobile.  
**Risk:** Cards wrap unpredictably or cause horizontal scroll; text truncation/overflow in small cards.  
**Expected Layout:**
- Desktop (>1200px): 4 columns
- Tablet (768–1200px): 2 columns
- Mobile (<768px): 1 column

---

### 3. Modal Variants (Inconsistent Centering & Overflow)
**Location:** `frontend-web/src/App.css` (lines 988–1100, 3026–3076, 3499–3600)  
**Modal Types:**
- `.login-modal` / `.login-card`
- `.ra-modal-overlay` / `.ra-modal` (Rescuer Account)
- `.rr-validation-modal-overlay` / `.rr-validation-modal` (Resource Request)
- `.archive-view-modal-overlay` / `.archive-view-modal` (Archive)
- `.sr-gen-overlay` / `.sr-gen-modal` (SitRep)

**Issues:**
- Different max-widths: 420px (login), 860px (ra), 900px (rr-validation)
- Inconsistent padding and border-radius
- `.ra-modal-body` and `.rr-validation-modal-body` have different overflow settings
- Tall forms may not scroll properly or close button becomes hidden

**Risk Areas:**
- Form height > viewport height → content not scrollable or modal cuts off
- Close button (`.modal-close`, `.ra-modal-close`, `.rr-validation-modal-close`) may be hidden on scroll
- Vertical centering differs across modals

---

### 4. Card Grid Rows (Feature / Photo / Workflow)
**Location:** `frontend-web/src/App.css` (lines 592–620)  
**Grid Definitions:**
- `.workflow-grid`, `.pillar-grid` → `grid-template-columns: repeat(4, minmax(0, 1fr))`
- `.feature-grid` → `grid-template-columns: repeat(3, minmax(0, 1fr))`
- `.photo-grid` → `grid-template-columns: repeat(3, minmax(0, 1fr))`

**Issue:** `.soft-card` elements have variable heights; no `align-items: stretch` on parent.  
**Risk:** Cards in same row misaligned vertically; text doesn't align at same baseline across rows.  
**Components:** [LoginLanding.jsx](frontend-web/src/components/login/LoginLanding.jsx) (feature cards, workflow cards, photo cards)

---

### 5. Mapping Map Panels (Fullscreen Toggle Overlap)
**Location:** `frontend-web/src/components/mapping/MappingMap.jsx` (lines 1–200)  
**Elements:**
- `.map-fullscreen-button`
- `.map-standby-note`
- `.map-layers-box`
- `.map-legend-box`

**Issue:** Fixed/absolute positioned controls (layers, legend, fullscreen button) may overlay each other or content when toggling fullscreen.  
**Risk:** User cannot see or click controls; map interaction blocked.

---

### 6. List Item Vertical Alignment (Icons + Text)
**Location:** Multiple card list components  
**Examples:**
- `.bc-log-item` (Broadcast): icon + text (Broadcast SidePanel)
- `.geotag-registry-card` (Mapping): icon + text + accuracy pill (Mapping Sidebar)
- `.map-site-card` (Mapping): icon + name + vacancy meter (Mapping Sidebar)
- `.dp-risk-card` (Dispatch): complex layout with metrics + actions (Dispatch Modal)
- `.archive-saved-item` (Archive): text layout with actions

**Issue:** Icons are centered (`place-items: center`), but text may not align to same vertical center; multi-line text shifts card height.  
**Risk:** Inconsistent spacing between items; icons and badges not visually aligned.

**Components:**
- [BroadcastSidePanel.jsx](frontend-web/src/components/broadcast/BroadcastSidePanel.jsx)
- [MappingSidebar.jsx](frontend-web/src/components/mapping/MappingSidebar.jsx)
- [DispatchModalForm.jsx](frontend-web/src/components/dispatch/DispatchModalForm.jsx)

---

### 7. Table Horizontal Scroll & Alignment
**Location:** `frontend-web/src/App.css` (lines 3320–3370)  
**Tables:**
- `.rr-table` (min-width: 1040px) – Resource Requests
- Household table (household-page)
- Rescuer table (dispatch-page)

**Issue:** Fixed min-width forces horizontal scroll even on wide screens; table header/row alignment breaks during scroll.  
**Risk:** Misaligned column headers and data rows; user cannot see all columns simultaneously on smaller screens.

---

### 8. Small Button & Close Button Alignment
**Location:** `frontend-web/src/App.css` (lines 86–161, 1008–1060)  
**Elements:**
- `.btn-sm` (min-height: 34px, padding: 0 10px)
- `.modal-close` (width: 36px, height: 36px, place-items: center)
- `.login-password-toggle` (position: absolute, top: 50%, right: 8px, transform: translateY(-50%))

**Issue:** Different centering approaches; `.login-password-toggle` uses transform, others use flexbox/grid.  
**Risk:** Icons or text not centered within buttons; misaligned in tight headers or form rows.

---

### 9. Form Layout Inconsistencies
**Location:** Multiple modal forms  
**Components:**
- `.ra-form-grid` – Rescuer Account form (2-column)
- `.profile-form-grid` – Profile edit (2-column)
- `.rr-form-grid` – Resource validation (2-column)
- `.login-form` – Login (1-column with special password field)

**Issue:** Different grid definitions; special cases like `.login-password-field` have position: relative with custom padding.  
**Risk:** Form labels, inputs, and error messages misaligned across pages; `.full` class row-span might not work consistently.

---

### 10. Contact Panel Grid Layout
**Location:** `frontend-web/src/App.css` (lines 861–900)  
**Issue:** `.contact-panel` uses `grid-template-columns: minmax(0, 0.85fr) minmax(320px, 1.15fr)` — rigid proportion.  
**Risk:** On tablets, left form may be squeezed; right form too large. No media query for smaller screens.

---

### 11. Archive Modal Vertical Alignment
**Location:** `frontend-web/src/components/archive/ArchiveRecordModal.jsx`  
**Issue:** `.archive-detail-grid` uses `grid-template-columns` with fixed sizes; labels and values may not align vertically if content height varies.

---

### 12. Dispatch Summary Cards
**Location:** `frontend-web/src/components/dispatch/DispatchSummary.jsx`  
**Issue:** Summary cards (similar structure to notification/profile summaries) may have the same 4-column grid breakage on mobile.

---

## Part 2: Test Cases (Manual & Automated)

### Test Case 1: Responsive Breakpoints
**Description:** Verify card/text/modal alignment at key breakpoints.  
**Viewports to Test:**
- 1440px (desktop large)
- 1024px (desktop medium)
- 768px (tablet)
- 480px (mobile)

**Assertions:**
- No horizontal scroll outside modals
- Cards reflow into appropriate columns (4→2→1 for summary strips)
- Modals center on screen with readable content
- Close buttons remain visible and clickable

**Test Pages:**
- `/dashboard` → notification-summary-strip
- `/profile` → profile-summary-strip
- `/login` → login modal + landing hero panels
- `/mapping` → map panels, legend, fullscreen toggle
- `/resources-requests` → rr-validation-modal

---

### Test Case 2: Modal Overflow & Scroll
**Description:** Test modals with tall/long content.  
**Actions:**
1. Open Rescuer Account modal (`/rescuers`) with create form (tall form)
2. Scroll form content inside modal
3. Verify close button remains visible at top
4. Open Resource Validation modal with all fields populated
5. Verify modal height capped at viewport; content scrollable

**Assertions:**
- Modal body scrolls independently (doesn't scroll page)
- Footer actions always visible (sticky or flex layout)
- Close button never hidden by scroll

---

### Test Case 3: Card Grid Row Alignment
**Description:** Verify cards in same row have consistent top/bottom alignment.  
**Actions:**
1. Load landing page → feature section
2. Inspect `.feature-card` elements in `.feature-grid`
3. Open DevTools → measure getBoundingClientRect() for each card

**Assertions:**
- All cards in row have same `.top` value (±2px tolerance)
- All cards have same height
- Text baseline aligns across cards

---

### Test Case 4: List Item Vertical Alignment
**Description:** Check icon + text alignment in card lists.  
**Actions:**
1. Open `/broadcast` → Broadcast log items (`.bc-log-item`)
2. Inspect each list item with getBoundingClientRect()
3. Measure icon vs text vertical center

**Assertions:**
- Icon center matches text center (±3px)
- Multi-line text doesn't shift card height inconsistently
- Spacing between items is uniform

**List Items to Test:**
- Broadcast log items
- Evacuation site cards (mapping)
- Dispatch team status cards
- Archive saved items

---

### Test Case 5: Table Horizontal Scroll & Alignment
**Description:** Verify table headers align with rows during scroll.  
**Actions:**
1. Open `/resources-requests` → Resource Requests table
2. Scroll table horizontally
3. Compare header position with row positions

**Assertions:**
- Header row stays at top (if sticky: position)
- Column alignment consistent during scroll
- No column misalignment between header and data rows

---

### Test Case 6: Map Fullscreen Toggle
**Description:** Verify map panels reposition correctly on fullscreen toggle.  
**Actions:**
1. Open `/mapping` → map view
2. Click "Full screen" button
3. Verify legend and layer controls reposition
4. Click "Back" to exit fullscreen
5. Verify controls return to original position

**Assertions:**
- Controls don't overlap map content
- Controls visible and clickable in both modes
- No layout shift/flicker during toggle

---

### Test Case 7: Modal Close Button Alignment
**Description:** Check close button is centered and accessible in all modal variants.  
**Actions:**
1. Open each modal type:
   - Login: `.modal-close` in `.login-top`
   - Rescuer Account: `.ra-modal-close` in `.ra-modal-head`
   - Resource Validation: `.rr-validation-modal-close` in `.rr-validation-modal-head`
2. Measure button position and size

**Assertions:**
- Close button vertically centered in header
- Button size consistent (32–36px)
- Icon inside button centered
- Button clickable at all zoom levels

---

### Test Case 8: Form Input Alignment
**Description:** Verify form labels, inputs, and error messages align across pages.  
**Actions:**
1. Open Rescuer Account form → inspect `.ra-form-grid`
2. Open Profile edit form → inspect `.profile-form-grid`
3. Open Resource Validation form → inspect `.rr-form-grid`

**Assertions:**
- Labels positioned consistently above inputs
- Input heights match across forms (34–44px)
- Error messages align under input
- `.full` class items span correct columns

---

### Test Case 9: Small Button Alignment (`.btn-sm`)
**Description:** Verify small buttons are vertically centered.  
**Actions:**
1. Inspect `.btn-sm` in dispatch modal, archive modals, etc.
2. Measure button height and icon/text position inside

**Assertions:**
- Button height: 34px minimum
- Icon vertically centered
- Text baseline centered
- Icon + text gap consistent (8px)

---

### Test Case 10: Accessibility Zoom (125%, 150%)
**Description:** Test alignment at browser zoom levels.  
**Actions:**
1. Set browser zoom to 125% (Ctrl + Plus)
2. Navigate key pages: landing, login, dashboard, mapping, dispatch
3. Check for overlap, overflow, or hidden content

**Assertions:**
- No overflow or horizontal scroll
- Modals still center on screen
- Buttons remain clickable
- Cards maintain row alignment

**Test Pages:**
- `/login`
- `/dashboard`
- `/mapping`
- `/dispatch`
- `/resources-requests`

---

### Test Case 11: Long Text Truncation
**Description:** Verify text wrapping and truncation doesn't break layout.  
**Actions:**
1. Populate card titles/notes with long strings (>60 chars)
2. Test in notification-summary-card, profile-summary-card, card list items

**Assertions:**
- Text wraps or truncates (no overflow)
- Card height adjusts if multi-line
- Sibling cards don't misalign

---

### Test Case 12: Empty State Alignment
**Description:** Verify cards with empty content or empty states align correctly.  
**Actions:**
1. Test pages with no active event (empty states appear)
2. Open modals with minimal content

**Assertions:**
- Empty state messages centered in card/modal
- Card height reasonable (no collapse)
- Layout stable across empty and full states

---

## Part 3: Automated Test Selectors (Playwright/Playwright Code)

### Selector Reference
```
/* Summary Strips */
.notification-summary-strip
.notification-summary-card
.profile-summary-strip
.profile-summary-card

/* Cards & Grids */
.soft-card
.feature-grid
.workflow-grid
.photo-grid

/* Modals */
.login-modal
.ra-modal-overlay
.rr-validation-modal-overlay
.archive-view-modal-overlay
.sr-gen-overlay

/* Map Controls */
.map-fullscreen-button
.map-layers-box
.map-legend-box

/* List Items */
.bc-log-item
.geotag-registry-card
.map-site-card
.dp-risk-card
.archive-saved-item

/* Tables */
.rr-table
.household-page table

/* Buttons */
.btn-sm
.modal-close
.ra-modal-close
.rr-validation-modal-close
```

---

## Part 4: Priority Issues (High → Low)

### 🔴 High Priority
1. **Summary card strips 4-column grid** – affects multiple pages (notifications, profile)
2. **Modal centering & overflow** – blocks user interaction on tall forms
3. **Card grid row alignment** – visual inconsistency on landing page
4. **List item vertical alignment** – affects card lists across all pages

### 🟡 Medium Priority
5. **Landing hero absolute panels** – overlap risk on narrow screens
6. **Table horizontal scroll** – usability issue on resource/household pages
7. **Form grid inconsistencies** – different alignment across modal forms
8. **Contact panel responsive layout** – squeezes on tablets

### 🟢 Low Priority
9. **Map fullscreen controls** – edge case, less frequent use
10. **Close button consistency** – minor alignment issue
11. **Long text truncation** – depends on content
12. **Empty state styling** – cosmetic

---

## Part 5: Recommendations

1. **Add responsive grid breakpoints** to all 4-column grids (media query @ 1024px, 768px)
2. **Standardize modal sizing** across all modal variants (max-width, padding, overflow)
3. **Use CSS Grid auto-sizing** for card rows (min-height or align-items: stretch)
4. **Test at zoom levels** (125%, 150%) in all test cases
5. **Use shared layout classes** for consistency (e.g., `.modal-centered`, `.form-grid-2col`)
6. **Add E2E tests** with Playwright to measure element positions and catch regressions

---

**Document End**
