# Interface guide

Updated: 2026-10-26. This user-requested interface refresh follows Phase 6; it
does not authorize or implement Phase 7.

## Direction

Use an office-oriented navy and blue interface with light surfaces, consistent
navigation, readable records and explicit preparation status. UI UX Pro Max 2.13.0
was found in the locally installed Claude plugin cache and read as requested.
Its government design-system query matched the Accessible & Ethical style/navy
palette; its marketing-oriented landing pattern was not applied. A narrower
`data dense dashboard` style search verified the operational dashboard direction.
Laravel form guidance and the `error summary validation` UX search informed feedback.

Do not display invented payroll totals, completion percentages, analytics,
approval controls or integrity-verification claims. Dashboard shortcuts link to
existing tools and follow the same gates as their destinations.

## Shared implementation

- `resources/css/app.css`: semantic tokens, typography, layout, controls, cards,
  tables, alerts, status labels, monetary breakdowns and responsive/reduced-motion rules.
- `views/layouts/app.blade.php`: guest split login, authorized navigation,
  breadcrumbs, current-role label, sign-out and skip-to-content landmark.
- `views/components/ui/`: decorative SVG icons, active navigation links and
  server-rendered field errors. The building mark is a generic application icon,
  not an official LGU seal.
- `views/partials/`: role-specific shortcuts and shared status/error feedback.
- `resources/js/workspace.js`: mobile navigation, password visibility, validation
  focus/field descriptions, keyboard-scrollable tables and submit progress feedback.

Keep system fonts and locally built assets. No remote font, CDN, new framework,
animation package or runtime dependency is needed for this design.

## Tokens

| Role | Value |
| --- | --- |
| Main ink / navigation | `#0f172a` |
| Body text | `#334155` |
| Secondary text | `#526176` |
| Primary action | `#0369a1` |
| Primary hover | `#075985` |
| Page background | `#f5f7fb` |
| Surface | `#ffffff` |
| Surface border | `#dfe5ee` |
| Input border | `#8290a4` |
| Error text | `#b42318` |

Use 8px control corners, 12px cards, predictable spacing and tabular numerals for
core totals. Retain exact saved monetary strings; the interface does not recalculate
or round payroll data. Status labels always include text, not color alone.

## Responsive and interaction behavior

- Sidebar remains available on desktop; at 900px and below the menu becomes a
  disclosure. Escape closes it and returns focus when focus was inside navigation.
  Without JavaScript, navigation remains visible and usable.
- At 600px and below, dashboard cards stack, list actions wrap and login uses a
  compact branded header. Inputs use 16px text on small screens. Table overflow
  stays within its scroll region and can be reached by keyboard.
- Error summaries receive focus after a validation response. Links connect to
  uniquely matched fields; ambiguous names across multiple forms are not guessed.
  Server-rendered inline errors and the summary remain visible without JavaScript.
- Password visibility uses a labelled, keyboard-operable toggle with pressed state.
  Password-manager autocomplete and paste remain supported.
- Input-line counts include their group name, announce changes and identify the
  existing 30-line limit. Removing a line returns focus to a remaining field or
  the add button. Saved revisions and employee-specific retry handling are unchanged.
- Primary actions show pending feedback on normal form submission; back/forward
  page restoration resets disabled submit controls. Motion is disabled when the
  reduced-motion preference is set.

## Verification boundary

See TESTING_STATUS.md for executed tests. The browser tool reported no connected
browser, and opening the in-app browser failed with `Browser is not available: iab`.
Consequently no rendered screenshot, viewport measurement, live JavaScript interaction
or accessibility-tree review was obtained. CSS breakpoints are implemented, not
visually accepted. Before office sign-off, review 375px, 768px, 1024px and 1440px,
200% zoom, keyboard navigation, menu open/close, password toggle, validation links,
input add/remove/limit feedback, pending buttons and back/forward restoration.
