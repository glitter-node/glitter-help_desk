# Glitter HelpDesk

Glitter HelpDesk is a standalone Gnuboard7 module for member-based customer support tickets.

## Included in 0.1.0

- Member ticket creation, search, status filtering, replies, and resolved-ticket reopening
- Staff ticket lists, assignment, priority and status changes, public replies, and internal notes
- Category and canned reply management, module settings, and ticket reports
- Ticket assignment and status history
- Korean and English layouts and backend messages

## Data handling

Tickets are retained when a member account is removed. The author reference is nulled while the ticket and messages remain; staff references are nulled as well. A ticket has no user-facing permanent delete operation. Internal notes are excluded from customer responses and require the `glitter-help_desk.notes.manage` permission to read.

Tables use the logical names `helpdesk_*`. Gnuboard7 applies its configured `g7_` connection prefix to produce the physical table names.

## Permissions

Customer permissions use the `user` context. Staff and management permissions use the `admin` context. The core permission table has one unique identifier across contexts, so staff replies use `glitter-help_desk.tickets.respond` while customer replies use the requested `glitter-help_desk.tickets.reply` permission.

## Assets

The module uses a target-scoped Vite IIFE build. `resources/js/index.ts` exports `GlitterHelpDesk`; build outputs are `dist/js/module.iife.js` and `dist/css/module.css`.
