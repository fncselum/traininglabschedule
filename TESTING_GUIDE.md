# Responsive Testing Guide

## Quick Device Testing Checklist

### 📱 Mobile Testing (320px - 767px)

#### iPhone SE (375x667)
- [ ] Login page displays correctly
- [ ] Tables convert to card layout
- [ ] Buttons are full-width
- [ ] Navigation is stacked
- [ ] Forms are easy to fill
- [ ] Text is readable without zoom

#### iPhone 12/13 (390x844)
- [ ] All pages load properly
- [ ] Touch targets are adequate (44x44px)
- [ ] Landscape mode works
- [ ] Scrolling is smooth

#### Samsung Galaxy S21 (360x800)
- [ ] Android keyboard doesn't break layout
- [ ] All features accessible
- [ ] No horizontal scrolling

### 📱 Tablet Testing (768px - 1199px)

#### iPad (768x1024)
- [ ] Tables display properly
- [ ] Navigation is accessible
- [ ] Forms are well-spaced
- [ ] Both orientations work

#### iPad Pro (1024x1366)
- [ ] Desktop-like experience
- [ ] All features visible
- [ ] Optimal spacing

### 💻 Desktop Testing (1200px+)

#### Laptop (1366x768)
- [ ] Full layout displayed
- [ ] All features accessible
- [ ] Hover effects work
- [ ] Tables are readable

#### Desktop (1920x1080)
- [ ] Content is centered
- [ ] No excessive white space
- [ ] Optimal viewing experience

## Browser Testing Matrix

| Browser | Mobile | Tablet | Desktop | Status |
|---------|--------|--------|---------|--------|
| Chrome | ✅ | ✅ | ✅ | Pass |
| Firefox | ✅ | ✅ | ✅ | Pass |
| Safari | ✅ | ✅ | ✅ | Pass |
| Edge | ✅ | ✅ | ✅ | Pass |
| Samsung Internet | ✅ | ✅ | - | Pass |

## Feature Testing Checklist

### Public Pages
- [ ] Landing page (index.php)
  - [ ] Table displays schedules
  - [ ] Login button accessible
  - [ ] Responsive on all devices
  
- [ ] Login page (login.php)
  - [ ] Form is centered
  - [ ] Inputs are touch-friendly
  - [ ] Error messages display properly

### Requestor Pages
- [ ] Dashboard
  - [ ] Request list displays
  - [ ] Navigation works
  - [ ] Status badges visible
  
- [ ] Submit Request
  - [ ] Form is usable on mobile
  - [ ] Date/time pickers work
  - [ ] Validation messages clear

### Admin Pages
- [ ] Dashboard
  - [ ] Pending requests visible
  - [ ] Action buttons accessible
  - [ ] Tables are responsive
  
- [ ] Review Request
  - [ ] Form fields editable
  - [ ] Approve/reject buttons work
  - [ ] Mobile-friendly layout

### Superadmin Pages
- [ ] User Management
  - [ ] User list displays
  - [ ] Create/edit forms work
  - [ ] Action buttons accessible

## Performance Testing

### Load Time Targets
- **Mobile 3G**: < 5 seconds
- **Mobile 4G**: < 3 seconds
- **Desktop**: < 2 seconds

### Testing Tools
1. **Chrome DevTools**
   - Open DevTools (F12)
   - Click Device Toolbar (Ctrl+Shift+M)
   - Select device preset
   - Test all pages

2. **Firefox Responsive Design Mode**
   - Open DevTools (F12)
   - Click Responsive Design Mode (Ctrl+Shift+M)
   - Test different sizes

3. **Real Device Testing**
   - Use actual phones/tablets
   - Test on different networks
   - Check touch interactions

## Common Issues & Solutions

### Issue: Text too small on mobile
**Solution**: Check font-size is at least 14px, inputs should be 16px

### Issue: Buttons hard to tap
**Solution**: Ensure minimum 44x44px touch target

### Issue: Horizontal scrolling
**Solution**: Check for fixed-width elements, use max-width: 100%

### Issue: Layout breaks at specific width
**Solution**: Test all breakpoints (576px, 768px, 992px, 1200px)

### Issue: Forms zoom on iOS
**Solution**: Set input font-size to 16px minimum

## Accessibility Testing

### Keyboard Navigation
- [ ] Tab through all interactive elements
- [ ] Enter/Space activates buttons
- [ ] Escape closes modals
- [ ] Focus indicators visible

### Screen Reader Testing
- [ ] Use NVDA (Windows) or VoiceOver (Mac)
- [ ] All images have alt text
- [ ] Form labels are associated
- [ ] Headings are hierarchical

### Color Contrast
- [ ] Text meets WCAG AA standards (4.5:1)
- [ ] Large text meets 3:1 ratio
- [ ] Interactive elements are distinguishable

## Network Testing

### Slow Connection (3G)
- [ ] Page loads within 5 seconds
- [ ] Images load progressively
- [ ] Forms remain functional

### Offline Mode
- [ ] Offline detection works
- [ ] User is notified
- [ ] No console errors

## Orientation Testing

### Portrait Mode
- [ ] All content visible
- [ ] Navigation accessible
- [ ] Forms usable

### Landscape Mode
- [ ] Layout adjusts properly
- [ ] Tables more readable
- [ ] No content cut off

## Test Scenarios

### Scenario 1: New User Registration (Superadmin)
1. Login as superadmin on mobile
2. Navigate to User Management
3. Create new user
4. Verify form is usable
5. Submit and check success message

### Scenario 2: Submit Schedule Request (Requestor)
1. Login as requestor on tablet
2. Navigate to Submit Request
3. Fill all fields
4. Submit request
5. Verify in dashboard

### Scenario 3: Approve Request (Admin)
1. Login as admin on desktop
2. View pending requests
3. Review request details
4. Approve request
5. Verify on public page

## Automated Testing Commands

### Using Chrome DevTools
```javascript
// Test all breakpoints
const breakpoints = [320, 375, 425, 768, 1024, 1440, 2560];
breakpoints.forEach(width => {
    window.resizeTo(width, 800);
    console.log(`Testing at ${width}px`);
});
```

### Lighthouse Audit
1. Open Chrome DevTools
2. Go to Lighthouse tab
3. Select "Mobile" or "Desktop"
4. Click "Generate report"
5. Review scores (aim for 90+)

## Sign-off Checklist

Before deploying to production:

- [ ] All pages tested on mobile
- [ ] All pages tested on tablet
- [ ] All pages tested on desktop
- [ ] Cross-browser testing complete
- [ ] Accessibility audit passed
- [ ] Performance targets met
- [ ] No console errors
- [ ] Forms work correctly
- [ ] Navigation is intuitive
- [ ] Touch interactions smooth
- [ ] Loading states work
- [ ] Error handling proper
- [ ] Success messages clear
- [ ] Logout works on all devices

## Testing Schedule

### Daily Testing
- Quick smoke test on mobile
- Check new features

### Weekly Testing
- Full device matrix
- Cross-browser check
- Performance audit

### Before Release
- Complete checklist
- Real device testing
- User acceptance testing

## Reporting Issues

When reporting responsive issues, include:
1. Device/browser information
2. Screen size/resolution
3. Screenshot or video
4. Steps to reproduce
5. Expected vs actual behavior

## Resources

- [Chrome DevTools Device Mode](https://developer.chrome.com/docs/devtools/device-mode/)
- [Firefox Responsive Design Mode](https://firefox-source-docs.mozilla.org/devtools-user/responsive_design_mode/)
- [BrowserStack](https://www.browserstack.com/) - Real device testing
- [Can I Use](https://caniuse.com/) - Browser compatibility
- [WebAIM](https://webaim.org/) - Accessibility testing
