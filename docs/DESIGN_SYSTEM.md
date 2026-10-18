# SUMMIT 2026 design system

## Identity
Use the original `docs/logo.png`, copied unchanged to `public/brand/summit-logo.png`. This is the only supplied brand image; website references and alternate logo treatments are unavailable. Its white lettering requires the deep navy background. Preserve aspect ratio with object-fit: contain; never recolor, stretch or regenerate it.

## Tokens and typography
Primary red #F20D19; deep navy #111A3D; white #FFFFFF; black #101114. Surface #F6F7FB, border #E2E5EE, muted text #5D6475. Red decoration retains the brand color; button text backgrounds use darker #CE101C for accessible white text. Use a local system sans-serif stack, 16px body, 14px secondary text, 24–36px page titles. Numeric metrics use tabular figures. Avoid remote fonts for low-bandwidth operation.

## Spacing, layout and components
Use a 4px base rhythm: 8, 12, 16, 24, 32, 48. Desktop navigation is 256px; content max-width 1440px. Cards use 12px radii, light borders and restrained shadows. Tables scroll within their container on small screens. Minimum interactive height is 44px. Primary buttons are red, secondary buttons white with border. Forms have persistent labels, visible required indications, linked validation feedback, autocomplete and appropriate input modes.

## Navigation and statuses
Display only authorized destinations and enforce permissions again on the server. Mobile navigation uses an accessible expanded-state toggle. Navy carries navigation and identity; red identifies the current destination and primary action. Status badges always include words: NOT CONTACTED (neutral), CONTACTED (green), UNREACHABLE (amber), UNASSIGNED (neutral). Do not convey state by color alone.

## Responsiveness and accessibility
Default light theme. Semantic custom properties make a later dark theme possible without duplicating component rules; no theme switch until dark colors are verified. Breakpoints: 640px, 768px, 1024px. Stack forms and cards at narrow widths; hide no critical labels. Provide skip navigation, landmarks, headings, focus rings, errors and polite status announcements. Respect reduced-motion preferences. Aim for WCAG AA contrast and keyboard access. Unconfigured session times and empty data are explicit; never insert sample operational metrics.

