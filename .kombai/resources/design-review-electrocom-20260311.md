# Design Review Results: ElectroCom Storefront (All Pages)

**Review Date**: 2026-03-11  
**Routes Reviewed**: `/` (Home), `/shop`, `/cart`, `/support`, `/faq`, `/checkout`, and global layout (Navbar, Sidebar, Footer)  
**Focus Areas**: Visual Design, UX/Usability, Responsive/Mobile, Accessibility, Micro-interactions, Consistency, Performance

---

## Summary

ElectroCom has a strong design foundation — a coherent blue color palette, good glassmorphism effects, fluid typography system, and thoughtful dark mode support. However, there are **25 issues** across all focus areas, including critical accessibility failures (non-button interactive elements, missing ARIA labels), severe performance problems (15s FCP, CLS of 1.763), a duplicate CSS class definition that silently overrides styles, and widespread use of hardcoded values that bypass the existing design token system.

---

## Issues

| # | Issue | Criticality | Category | Location |
|---|-------|-------------|----------|----------|
| 1 | **FCP of ~15 seconds** — UI is blocked waiting for `localhost:8000` API to timeout before rendering anything useful. No timeout/fallback renders the page frozen on skeleton for users with a slow/offline backend. | 🔴 Critical | Performance | `storefront/src/App.jsx:140-193` |
| 2 | **CLS score of 1.763** (poor, threshold is <0.1) — layout shifts drastically when viewport resizes due to sidebar/nav geometry changing. | 🔴 Critical | Performance | `storefront/src/style.css:466-676` (sidebar/main-wrapper) |
| 3 | **Interactive `<div>` elements without button semantics** — Map, Notifications, Search toggle, and Menu items in Navbar are `<div className="sidebar-icon btn">` with `onClick`, not `<button>`. Screen readers cannot navigate to or activate them. | 🔴 Critical | Accessibility | `storefront/src/components/Navbar.jsx:81,185-192` |
| 4 | **FAQ cards are non-semantic clickable divs** — `<div className="faq-card glass" onClick={...}>` with no `role="button"` or `tabIndex`, making them keyboard and screen-reader inaccessible. | 🔴 Critical | Accessibility | `storefront/src/pages/Support.jsx:26` |
| 5 | **No `aria-label` on icon-only nav buttons** — Map, Bell, Cart, Theme toggle, and Menu icons in Navbar have zero accessible text. Screen readers announce them as unlabeled buttons. | 🟠 High | Accessibility | `storefront/src/components/Navbar.jsx:81,185-209` |
| 6 | **Duplicate `.btn-secondary` class definition** — Defined at line 1784 with `padding: 12px 24px` and again at line 3081 with `padding: 12px` (no sides). The second silently overrides the first across the entire app, breaking intended button sizing. | 🟠 High | Consistency | `storefront/src/style.css:1784` and `3081` |
| 7 | **Login button absent on mobile nav** — At 390px viewport the Login button is completely absent. The `btn-login` class shrinks but no conditional rendering ensures it's visible. Unauthenticated mobile users cannot sign in from the nav. | 🟠 High | Responsive/Mobile | `storefront/src/components/Navbar.jsx:261` ; `storefront/src/style.css:2843-2851` |
| 8 | **`setLoading(false)` only fires when `products.length === 0`** — If the API initially succeeds but later fails, the loading spinner never re-appears and users see stale data with no feedback. | 🟠 High | UX/Usability | `storefront/src/App.jsx:143,191` |
| 9 | **Empty state shows `No products found matching ""`** — When the API returns zero products the empty state message exposes the empty `searchQuery` string (`""`). Should display a friendlier "No products available right now" with a retry/refresh CTA. | 🟠 High | UX/Usability | `storefront/src/pages/Home.jsx:28-30` |
| 10 | **Sidebar active state defined twice with conflicting styles** — `.sidebar-icon.active` at line 574 sets `background: #ffffff; color: var(--primary-blue)` but is overridden at line 2750 with `background: rgba(255,255,255,0.15); color: white`, making active state invisible on desktop. | 🟠 High | Consistency | `storefront/src/style.css:574` and `2750` |
| 11 | **Footer "About Us" and "Contact Support" both point to `/support`** — Duplicate redundant links in the Support column. Should link to a dedicated About page or split the content. | 🟡 Medium | UX/Usability | `storefront/src/components/Footer.jsx:61,65` |
| 12 | **Global `* { transition: ... }` causes performance issues** — Applying `transition` to every DOM element (including deeply nested ones) prevents the browser from compositing layers efficiently and causes jank during scroll/hover on complex pages. | 🟡 Medium | Performance | `storefront/src/style.css:162-168` |
| 13 | **Home page heading bypasses design token** — `fontSize: '24px'` hardcoded in JSX instead of using `var(--font-size-xl)` (which is fluid 24px→32px). Heading won't scale on large screens. | 🟡 Medium | Visual Design | `storefront/src/pages/Home.jsx:19` |
| 14 | **Support page headings bypass design tokens** — `fontSize: '32px'`, `fontSize: '28px'` hardcoded instead of `var(--font-size-2xl)` / `var(--font-size-xl)`. Same issue across Support, FAQ, and other pages. | 🟡 Medium | Visual Design | `storefront/src/pages/Support.jsx:91,119` |
| 15 | **Search input uses hardcoded `font-size: 15px`** instead of `var(--font-size-sm)`. Won't scale with fluid typography system. | 🟡 Medium | Consistency | `storefront/src/style.css:785` |
| 16 | **Category dropdown hidden at ≤1024px with no mobile replacement** — The category filter is `display:none` on tablet/mobile with no alternative. Mobile users have no way to filter products by category. | 🟡 Medium | Responsive/Mobile | `storefront/src/style.css:1696-1700` |
| 17 | **Inconsistent border-radius values** — Design tokens define `--radius-sm: 8px` and `--radius-lg: 16px` but inline styles throughout use `24px`, `14px`, `20px`, `50%` etc. (e.g. Support.jsx lines 99, 185, 203). | 🟡 Medium | Consistency | `storefront/src/pages/Support.jsx:99,185,203` ; `storefront/src/style.css:387,403` |
| 18 | **Top nav `max-width: 1400px` misaligns with sidebar on ultra-wide screens** — The sidebar is full-height fixed at `left: 12px` while nav content centers at `max-width: 1400px`. On screens >1440px the nav content floats right of the sidebar visually. | 🟡 Medium | Responsive/Mobile | `storefront/src/style.css:697-700,666-676` |
| 19 | **No focus-visible styles on product cards and FAQ cards** — Clicking or tabbing to cards shows no visible focus ring. Only nav links have `focus-visible` outlined. | 🟡 Medium | Accessibility | `storefront/src/style.css:631` (only on `.sidebar .nav-link`) |
| 20 | **Support page uses 100% inline styles instead of classes** — All layout in Support.jsx is done via `style={{}}` props, making it impossible to override with themes and creating a massive maintenance surface. | 🟡 Medium | Consistency | `storefront/src/pages/Support.jsx` (entire file) |
| 21 | **WhatsApp button has `opacity: 0.8`** hardcoded, making it look disabled when it's active. Reduces perceived trust and affordance. | ⚪ Low | Visual Design | `storefront/src/pages/Support.jsx:235` |
| 22 | **Product card `::before` pseudo-element extends 20px below** — Used to prevent hover gap, but this creates a 20px invisible hitbox below every card, potentially triggering unintended hover effects on content below. | ⚪ Low | UX/Usability | `storefront/src/style.css:1950-1960` |
| 23 | **Footer Contact column breaks grid layout** — The footer is a 4-column grid but the Contact column appears as a 5th column below on medium screens, breaking the layout. Should restructure to accommodate 5 columns or merge columns. | ⚪ Low | Visual Design | `storefront/src/style.css` (footer grid not visible in file — check footer styles) |
| 24 | **No skeleton state for empty/failed API call** — When products fail to load, the UI switches from skeleton cards to a blank card with text, with no animation or retry button. | ⚪ Low | Micro-interactions | `storefront/src/pages/Home.jsx:27-30` |
| 25 | **Dark mode text shadows applied globally** — `text-shadow: 0 1px 1px rgba(0,0,0,0.2)` applied to ALL text in `.dark-mode`, including headings and muted text. This can blur thin text at small sizes and conflict with button text. | ⚪ Low | Visual Design | `storefront/src/style.css:120-122` |

---

## Criticality Legend
- 🔴 **Critical**: Breaks functionality or violates accessibility standards
- 🟠 **High**: Significantly impacts user experience or design quality
- 🟡 **Medium**: Noticeable issue that should be addressed
- ⚪ **Low**: Nice-to-have improvement

---

## Next Steps (Prioritized)

### Immediate (Week 1)
1. **Fix #3, #4, #5** — Replace interactive `div` elements with `<button>` tags; add `aria-label` to all icon buttons. (30 min)
2. **Fix #6** — Remove duplicate `.btn-secondary` definition at line 3081 and consolidate. (5 min)
3. **Fix #10** — Remove the conflicting sidebar active state at line 2750 or scope it to mobile only. (10 min)
4. **Fix #1** — Add an `AbortController` with timeout (e.g. 5s) to API fetches so the UI doesn't freeze for 15s. (1 hour)
5. **Fix #7** — Ensure Login button is always visible on mobile. (15 min)

### Short Term (Week 2)
6. **Fix #9** — Improve empty state: show meaningful message with retry CTA and an illustration.
7. **Fix #2** — Audit and minimize CLS by reserving space for the sidebar/nav during initial paint.
8. **Fix #16** — Add a mobile-friendly category filter (e.g. horizontal scroll pills below the nav or a bottom sheet).
9. **Fix #12** — Replace global `* { transition }` with scoped transitions on specific components.
10. **Fix #13, #14, #15** — Replace hardcoded font sizes with design token CSS variables.

### Ongoing
11. **Fix #17** — Create a `--radius-xl: 24px` token and use it consistently. 
12. **Fix #20** — Migrate Support.jsx inline styles to CSS classes for maintainability.
