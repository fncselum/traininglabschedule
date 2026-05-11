# 📐 Responsive Design Visual Guide

## Breakpoint System

```
┌─────────────────────────────────────────────────────────────────┐
│                    RESPONSIVE BREAKPOINTS                        │
└─────────────────────────────────────────────────────────────────┘

320px          576px          768px          992px         1200px
  │              │              │              │              │
  ├──────────────┤              │              │              │
  │   XS (xs)    │              │              │              │
  │ Extra Small  │              │              │              │
  │ Small Phones │              │              │              │
  └──────────────┴──────────────┤              │              │
                 │   SM (sm)    │              │              │
                 │    Small     │              │              │
                 │ Large Phones │              │              │
                 └──────────────┴──────────────┤              │
                                │   MD (md)    │              │
                                │   Medium     │              │
                                │   Tablets    │              │
                                └──────────────┴──────────────┤
                                               │   LG (lg)    │
                                               │    Large     │
                                               │Small Laptops │
                                               └──────────────┴──────────────┐
                                                              │   XL (xl)    │
                                                              │ Extra Large  │
                                                              │   Desktops   │
                                                              └──────────────┘
```

---

## 📱 Device Examples by Breakpoint

### XS - Extra Small (< 576px)
```
┌─────────────┐
│   iPhone SE │  375 x 667
│             │
│   [≡ Menu]  │  ← Hamburger menu
│             │
│  ┌────────┐ │
│  │ Card 1 │ │  ← Tables become cards
│  └────────┘ │
│  ┌────────┐ │
│  │ Card 2 │ │
│  └────────┘ │
│             │
│ [Button 1]  │  ← Full-width buttons
│ [Button 2]  │
│             │
└─────────────┘
```

**Devices:**
- iPhone SE (375px)
- Samsung Galaxy S8 (360px)
- Small Android phones

**Features:**
- Card-based table layout
- Stacked navigation
- Full-width buttons
- Reduced font sizes
- Minimal padding

---

### SM - Small (576px - 767px)
```
┌──────────────────┐
│   iPhone 12 Pro  │  390 x 844
│                  │
│    [≡ Menu]      │  ← Still hamburger menu
│                  │
│  ┌────────────┐  │
│  │  Card 1    │  │  ← Wider cards
│  └────────────┘  │
│                  │
│ [Button 1]       │  ← Still full-width
│ [Button 2]       │
│                  │
└──────────────────┘
```

**Devices:**
- iPhone 12/13 (390px)
- iPhone 12/13 Pro Max (428px)
- Large Android phones

**Features:**
- Wider card layouts
- Better spacing
- Larger touch targets
- Improved typography

---

### MD - Medium (768px - 991px)
```
┌────────────────────────────┐
│         iPad               │  768 x 1024
│                            │
│  Training Lab Schedule     │
│  [Home] [Login] [About]    │  ← Horizontal menu
│                            │
│  ┌──────────────────────┐  │
│  │ Table with scroll    │  │  ← Scrollable table
│  │ ← swipe →            │  │
│  └──────────────────────┘  │
│                            │
│  [Button 1] [Button 2]     │  ← Side-by-side buttons
│                            │
└────────────────────────────┘
```

**Devices:**
- iPad (768px)
- Android tablets
- Small laptops

**Features:**
- Horizontal navigation
- Scrollable tables
- Side-by-side buttons
- Hybrid layout
- Touch + hover support

---

### LG - Large (992px - 1199px)
```
┌──────────────────────────────────────┐
│        Laptop Screen                 │  1024 x 768
│                                      │
│  Training Laboratory Schedule        │
│  [Home] [Dashboard] [Requests] [Logout]
│                                      │
│  ┌────────────────────────────────┐  │
│  │  Full Table Layout             │  │
│  │  [Edit] [Delete] [View]        │  │
│  └────────────────────────────────┘  │
│                                      │
│  [Save] [Cancel] [Delete]            │
│                                      │
└──────────────────────────────────────┘
```

**Devices:**
- Small laptops (1024px)
- Netbooks
- Older monitors

**Features:**
- Full table layout
- All features visible
- Hover effects
- Keyboard shortcuts
- Multi-column layouts

---

### XL - Extra Large (≥ 1200px)
```
┌────────────────────────────────────────────────────────────┐
│              Desktop Monitor                               │  1920 x 1080
│                                                            │
│  Training Laboratory Schedule System                       │
│  [Dashboard] [Pending Requests] [Schedules] [Users] [Logout]
│                                                            │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  Full-Width Table with All Columns                   │  │
│  │  Date │ Title │ Time │ Location │ Status │ Actions   │  │
│  │  ─────┼───────┼──────┼──────────┼────────┼─────────  │  │
│  │  ...  │  ...  │ ...  │   ...    │  ...   │ [E][D]   │  │
│  └──────────────────────────────────────────────────────┘  │
│                                                            │
│  [Primary Action] [Secondary] [Tertiary] [Cancel]          │
│                                                            │
└────────────────────────────────────────────────────────────┘
```

**Devices:**
- Desktop monitors (1920px+)
- Large laptops
- 2K/4K displays

**Features:**
- Maximum content width
- All columns visible
- Rich interactions
- Advanced features
- Optimal spacing

---

## 🔄 Layout Transformations

### Navigation Menu

#### Mobile (< 768px)
```
┌─────────────┐
│ [≡]  Logo   │  ← Hamburger button
└─────────────┘

When clicked:
┌─────────────┐
│ [×]  Logo   │
│             │
│ ┌─────────┐ │
│ │ Home    │ │  ← Slide-out menu
│ │ Login   │ │
│ │ About   │ │
│ └─────────┘ │
└─────────────┘
```

#### Desktop (≥ 768px)
```
┌────────────────────────────────┐
│ Logo    [Home] [Login] [About] │  ← Horizontal menu
└────────────────────────────────┘
```

---

### Table Layout

#### Mobile (< 576px) - Card View
```
┌──────────────────┐
│ Date             │
│ May 8, 2026      │
│                  │
│ Title            │
│ Training A       │
│                  │
│ Time             │
│ 9:00 AM - 12:00  │
│                  │
│ Status           │
│ [Approved]       │
│                  │
│ [Edit] [Delete]  │
└──────────────────┘
```

#### Tablet (576px - 991px) - Scrollable
```
┌────────────────────────────────┐
│ ← Swipe to see more →          │
│ ┌──────────────────────────┐   │
│ │Date │Title │Time │Status │   │
│ │─────┼──────┼─────┼───────│   │
│ │5/8  │Train │9:00 │[App]  │   │
│ └──────────────────────────┘   │
└────────────────────────────────┘
```

#### Desktop (≥ 992px) - Full Table
```
┌────────────────────────────────────────────────┐
│ Date │ Title │ Time │ Location │ Status │ Actions │
│──────┼───────┼──────┼──────────┼────────┼─────────│
│ 5/8  │Train A│ 9:00 │ Lab 101  │[Approved]│[E][D] │
│ 5/9  │Train B│ 1:00 │ Lab 102  │[Pending] │[E][D] │
└────────────────────────────────────────────────┘
```

---

### Button Layout

#### Mobile (< 768px)
```
┌──────────────┐
│ [  Save    ] │  ← Full width
│ [  Cancel  ] │
│ [  Delete  ] │
└──────────────┘
```

#### Desktop (≥ 768px)
```
┌────────────────────────────┐
│ [Save] [Cancel] [Delete]   │  ← Side by side
└────────────────────────────┘
```

---

## 🎨 Visual Hierarchy

### Font Sizes Across Breakpoints

```
Element          XS      SM      MD      LG      XL
─────────────────────────────────────────────────────
Body            14px    15px    15px    15px    16px
H1              1.2rem  1.4rem  1.5rem  1.8rem  1.8rem
H2              1.2rem  1.3rem  1.5rem  1.75rem 2rem
H3              1.1rem  1.2rem  1.25rem 1.5rem  1.5rem
Button          0.85rem 0.9rem  0.95rem 1rem    1rem
Table           0.85rem 0.85rem 0.875rem 1rem   1rem
```

### Spacing Scale

```
Element          XS      SM      MD      LG      XL
─────────────────────────────────────────────────────
Container        10px    15px    15px    20px    20px
Card Padding     1rem    1.5rem  1.5rem  2rem    2.5rem
Button Padding   0.7rem  0.75rem 0.85rem 0.85rem 0.85rem
Section Gap      1rem    1.5rem  2rem    2.5rem  3rem
```

---

## 📊 Touch Target Sizes

### Minimum Touch Targets (WCAG Compliant)

```
┌────────────────────────────────┐
│                                │
│    ┌──────────────────┐        │
│    │                  │        │
│    │   44px × 44px    │  ← Minimum
│    │                  │
│    └──────────────────┘        │
│                                │
└────────────────────────────────┘

Examples:
- Buttons: 44px height minimum
- Links: 44px height minimum
- Form inputs: 44px height minimum
- Icons: 44px × 44px minimum
```

---

## 🎯 Responsive Patterns

### Pattern 1: Stacking
```
Desktop:                Mobile:
┌─────┬─────┐          ┌─────┐
│  A  │  B  │    →     │  A  │
└─────┴─────┘          ├─────┤
                       │  B  │
                       └─────┘
```

### Pattern 2: Hiding
```
Desktop:                Mobile:
┌─────┬─────┬─────┐    ┌─────┐
│  A  │  B  │  C  │ →  │  A  │  (B & C hidden)
└─────┴─────┴─────┘    └─────┘
```

### Pattern 3: Reordering
```
Desktop:                Mobile:
┌─────┬─────┐          ┌─────┐
│  A  │  B  │    →     │  B  │  (Priority first)
└─────┴─────┘          ├─────┤
                       │  A  │
                       └─────┘
```

### Pattern 4: Expanding
```
Desktop:                Mobile:
┌─────────┐            ┌─────────┐
│ Summary │      →     │ Summary │
└─────────┘            ├─────────┤
                       │ Details │  (Expanded)
                       └─────────┘
```

---

## 🔍 Testing Viewports

### Chrome DevTools Presets
```
Device              Width    Height   DPR
─────────────────────────────────────────
iPhone SE           375px    667px    2
iPhone 12 Pro       390px    844px    3
iPhone 12 Pro Max   428px    926px    3
Pixel 5             393px    851px    2.75
Samsung Galaxy S20  360px    800px    3
iPad                768px    1024px   2
iPad Pro            1024px   1366px   2
Surface Pro 7       912px    1368px   2
```

### Custom Test Sizes
```
┌────────────────────────────────┐
│ 320px  - Minimum mobile        │
│ 375px  - iPhone SE             │
│ 414px  - iPhone Plus           │
│ 768px  - iPad portrait         │
│ 1024px - iPad landscape        │
│ 1366px - Laptop                │
│ 1920px - Desktop               │
│ 2560px - Large desktop         │
└────────────────────────────────┘
```

---

## 📱 Orientation Handling

### Portrait Mode
```
┌─────────┐
│         │
│ Header  │
│         │
├─────────┤
│         │
│         │
│ Content │
│         │
│         │
├─────────┤
│ Footer  │
└─────────┘
```

### Landscape Mode
```
┌────────────────────────┐
│ Header                 │
├────────────────────────┤
│                        │
│      Content           │
│                        │
├────────────────────────┤
│ Footer                 │
└────────────────────────┘
```

---

## ✅ Responsive Checklist

### Visual Check
- [ ] No horizontal scrolling
- [ ] All text is readable
- [ ] Images scale properly
- [ ] Buttons are tappable
- [ ] Forms are usable
- [ ] Navigation works
- [ ] Tables are accessible
- [ ] Spacing is consistent

### Functional Check
- [ ] All features work
- [ ] Touch gestures work
- [ ] Keyboard navigation works
- [ ] Forms submit properly
- [ ] Links are clickable
- [ ] Menus open/close
- [ ] Modals display correctly
- [ ] Alerts are visible

### Performance Check
- [ ] Page loads quickly
- [ ] Animations are smooth
- [ ] No layout shifts
- [ ] Images load efficiently
- [ ] Scripts execute fast
- [ ] No console errors

---

## 🎨 Color & Contrast

### Color Palette (WCAG AA Compliant)
```
Primary Green:    #2e7d32  ████  (Contrast: 4.5:1)
Secondary Green:  #388e3c  ████  (Contrast: 4.5:1)
Light Green:      #66bb6a  ████  (Contrast: 3:1)
Dark Green:       #1b5e20  ████  (Contrast: 7:1)
Background:       #e8f5e9  ████  (Contrast: 1.2:1)
Text:             #1a1a1a  ████  (Contrast: 16:1)
```

---

## 📐 Grid System

### 12-Column Grid
```
Desktop (≥992px):
┌──┬──┬──┬──┬──┬──┬──┬──┬──┬──┬──┬──┐
│ 1│ 2│ 3│ 4│ 5│ 6│ 7│ 8│ 9│10│11│12│
└──┴──┴──┴──┴──┴──┴──┴──┴──┴──┴──┴──┘

Tablet (768-991px):
┌────┬────┬────┬────┬────┬────┐
│ 1-2│ 3-4│ 5-6│ 7-8│ 9-10│11-12│
└────┴────┴────┴────┴────┴────┘

Mobile (<768px):
┌──────────────┐
│    1-12      │  (Full width)
└──────────────┘
```

---

**Last Updated**: May 8, 2026
**Version**: 2.0
**For**: Training Laboratory Schedule System
