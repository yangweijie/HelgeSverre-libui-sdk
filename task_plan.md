# Task Plan: Composite GUI Components

## Goal
Build a set of reusable Composite-based GUI components in `src/Fields/`, grouped into two phases: Form Fields (Group 1) then Picker/Slider Fields (Group 2).

## Current Phase
Phase 1: Group 1 — Form Fields

## Phases

### Phase 1: Group 1 — Form Fields (TextField, PasswordField, NumberField, SearchField)
- [ ] Create `src/Fields/` directory
- [ ] `src/Fields/TextField.php` — Label + Entry, HasValue(string), emits 'change'
- [ ] `src/Fields/PasswordField.php` — Label + PasswordEntry, HasValue(string), emits 'change'
- [ ] `src/Fields/NumberField.php` — Label + Spinbox, HasValue(int), emits 'change'
- [ ] `src/Fields/SearchField.php` — Label + SearchEntry, HasValue(string), emits 'change'
- [ ] PHP lint all files
- **Status:** in_progress

### Phase 2: Group 2 — Picker & Slider Fields
- [x] `src/Fields/FilePickerField.php` — Entry(readonly) + "Browse" button, opens native file dialog
- [x] `src/Fields/SliderField.php` — Slider + value label, updates label on drag
- [x] PHP lint all files
- **Status:** complete

### Phase 3: Verify & Commit
- [x] Run php -l on all new files
- [x] Run existing tests
- [x] Git commit
- **Status:** complete

## Key Questions
1. Should FilePickerField accept a parent Window or hold its own reference?
2. Should SliderField update label in real-time or only on release?

## Decisions Made
| Decision | Rationale |
|----------|-----------|
| All fields use horizontal Box(root) = Label + Input | Consistent form layout |
| Fields use EmitsEvents trait for 'change' event | Bridges upstream onChanged → Composite event model |
| FilePickerField accepts Window in constructor | Dialogs need parent for native modal |
| SliderField updates label on both onChange AND onRelease | Real-time feedback + final value accuracy |

## Errors Encountered
| Error | Attempt | Resolution |
|-------|---------|------------|
