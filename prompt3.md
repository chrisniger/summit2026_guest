# SUMMIT 2026 — OFFICIAL DESIGN SYSTEM IMPLEMENTATION

Update the SUMMIT 2026 Laravel application to follow the official Summit Bible Church / SUMMIT 2026 visual identity.

## 1. Review brand assets

The project `docs/` directory contains the official Summit logo and website design reference images.

Before implementing frontend screens:

1. Locate and inspect all logo and design reference images in `docs/`.
2. Identify the official logo variations and their appropriate background treatments.
3. Preserve the original logo proportions, typography and appearance.
4. Do not replace the official logo with generated alternatives.
5. Create a reusable design system based on the supplied reference images.

## 2. Brand colors

Use the following initial design tokens:

- Primary Red: `#F20D19`
- Deep Navy Blue: `#111A3D`
- White: `#FFFFFF`
- Black: `#101114`

Secondary neutrals may be introduced for borders, muted backgrounds and typography.

Use semantic colors for operational statuses, ensuring sufficient contrast and accessibility.

## 3. Design requirements

Create a premium, professional, modern administrative interface.

Use:
- Laravel Blade and Livewire
- Tailwind CSS
- Alpine.js where appropriate
- Reusable UI components
- Responsive layouts
- Consistent spacing and typography
- Accessible form elements
- Subtle transitions and micro-interactions

Avoid excessive gradients, decorative elements, unnecessary animations and generic-looking AI dashboard styling.

## 4. Application layouts

Implement consistent branding across:

- Login page
- Super Admin Dashboard
- Info Com Dashboard
- Attendance Check-In
- Registration Forms
- Follow-Up Admin Dashboard
- Guest Data Entry
- Follow-Up Officer Dashboard
- Reports and Analytics
- User Management

The mobile attendance and guest entry interfaces must prioritize speed, readability and large touch targets.

## 5. Theme support

Implement a light theme as the default.

Structure the design tokens to support optional dark mode without duplicating styles.

## 6. Logo handling

Store production-ready logo assets in the application's public asset directory while keeping the original reference files in `docs/`.

Use the official Summit logo appropriately across authentication pages, dashboards and navigation.

## 7. Documentation

Create or update:

`docs/DESIGN_SYSTEM.md`

Include:
- Color tokens
- Typography
- Spacing
- Buttons
- Form controls
- Navigation
- Cards and tables
- Status indicators
- Mobile breakpoints
- Logo usage guidelines
- Accessibility rules

## 8. Verification

Ensure the visual identity is consistent across every user role.

Run the frontend build and verify mobile layouts.

Do not change any confirmed conference attendance or Guest Service business rules while implementing the design system.