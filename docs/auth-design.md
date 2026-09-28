# FoodSave 2.0 Authentication Design

## Palette

- Background: `#F4FBF7`
- Surface: `#FFFFFF`
- Primary: `#166534`
- Primary dark: `#14532D`
- Accent: `#22C55E`
- Accent soft: `#DCFCE7`
- Text: `#0F172A`
- Muted: `#475569`
- Border: `#DCEFE3`
- Error: `#B91C1C`
- Warning: `#92400E`

## Design decisions

- Opaque white authentication surfaces.
- No `backdrop-filter`, blur, transparency or decorative glassmorphism.
- Dark text on white inputs/cards for reliable contrast.
- Green is reserved for primary actions and FoodSave identity.
- Error/warning colors are paired with readable dark text rather than low-contrast white-on-yellow/red.
- Responsive spacing and input sizing for mobile.
