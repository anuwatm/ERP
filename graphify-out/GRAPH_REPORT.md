# Graph Report - backend/app  (2026-08-26)

## Corpus Check
- Corpus is ~25,398 words - fits in a single context window. You may not need a graph.

## Summary
- 932 nodes · 2268 edges · 64 communities (54 shown, 10 thin omitted)
- Extraction: 96% EXTRACTED · 4% INFERRED · 0% AMBIGUOUS · INFERRED: 87 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- [[_COMMUNITY_Customer Deal Flow|Customer Deal Flow]]
- [[_COMMUNITY_Inertia Middleware|Inertia Middleware]]
- [[_COMMUNITY_Dashboard Aggregates|Dashboard Aggregates]]
- [[_COMMUNITY_Product Catalog|Product Catalog]]
- [[_COMMUNITY_User Roles|User Roles]]
- [[_COMMUNITY_Org Structure|Org Structure]]
- [[_COMMUNITY_Commercial Documents|Commercial Documents]]
- [[_COMMUNITY_Task Workflow|Task Workflow]]
- [[_COMMUNITY_Project Delivery|Project Delivery]]
- [[_COMMUNITY_Invoice Finance|Invoice Finance]]
- [[_COMMUNITY_Supplier Tax Reports|Supplier Tax Reports]]
- [[_COMMUNITY_Files Payments|Files Payments]]
- [[_COMMUNITY_Inventory Receipts|Inventory Receipts]]
- [[_COMMUNITY_Notifications|Notifications]]
- [[_COMMUNITY_Branch Models|Branch Models]]
- [[_COMMUNITY_Profile Auth|Profile Auth]]
- [[_COMMUNITY_Purchase Orders|Purchase Orders]]
- [[_COMMUNITY_Quotations|Quotations]]
- [[_COMMUNITY_Shared Models|Shared Models]]
- [[_COMMUNITY_Deal Model|Deal Model]]
- [[_COMMUNITY_Contacts|Contacts]]
- [[_COMMUNITY_Invoice Model|Invoice Model]]
- [[_COMMUNITY_Audit Verify|Audit Verify]]
- [[_COMMUNITY_Login Request|Login Request]]
- [[_COMMUNITY_Mail Templates|Mail Templates]]
- [[_COMMUNITY_Project Model|Project Model]]
- [[_COMMUNITY_Invite Accept|Invite Accept]]
- [[_COMMUNITY_Login Session|Login Session]]
- [[_COMMUNITY_Customer Model|Customer Model]]
- [[_COMMUNITY_Payment Model|Payment Model]]
- [[_COMMUNITY_PO Model|PO Model]]
- [[_COMMUNITY_Quotation Model|Quotation Model]]
- [[_COMMUNITY_Task Model|Task Model]]
- [[_COMMUNITY_Registration|Registration]]
- [[_COMMUNITY_Expense Model|Expense Model]]
- [[_COMMUNITY_Role Model|Role Model]]
- [[_COMMUNITY_Password Confirm|Password Confirm]]
- [[_COMMUNITY_New Password|New Password]]
- [[_COMMUNITY_Password Reset|Password Reset]]
- [[_COMMUNITY_Notification Settings|Notification Settings]]
- [[_COMMUNITY_Billing Model|Billing Model]]
- [[_COMMUNITY_Contact Model|Contact Model]]
- [[_COMMUNITY_CN DN Model|CN DN Model]]
- [[_COMMUNITY_DO Model|DO Model]]
- [[_COMMUNITY_GRN Model|GRN Model]]
- [[_COMMUNITY_PR Model|PR Model]]
- [[_COMMUNITY_Invoice Policy|Invoice Policy]]
- [[_COMMUNITY_Product Policy|Product Policy]]
- [[_COMMUNITY_Verify Prompt|Verify Prompt]]
- [[_COMMUNITY_Permission Middleware|Permission Middleware]]
- [[_COMMUNITY_Department Model|Department Model]]
- [[_COMMUNITY_Invoice Item Model|Invoice Item Model]]
- [[_COMMUNITY_Organization Model|Organization Model]]
- [[_COMMUNITY_Quotation Item|Quotation Item]]
- [[_COMMUNITY_Verify Notification|Verify Notification]]
- [[_COMMUNITY_Password Update|Password Update]]
- [[_COMMUNITY_Sales Dashboard|Sales Dashboard]]
- [[_COMMUNITY_Billing Lines|Billing Lines]]
- [[_COMMUNITY_CN DN Items|CN DN Items]]
- [[_COMMUNITY_Project Members|Project Members]]
- [[_COMMUNITY_Activity Policy|Activity Policy]]
- [[_COMMUNITY_App Provider|App Provider]]
- [[_COMMUNITY_Baht Text|Baht Text]]

## God Nodes (most connected - your core abstractions)
1. `Controller` - 71 edges
2. `AuditLog` - 32 edges
3. `OrganizationStructureController` - 26 edges
4. `SalesAccess` - 24 edges
5. `Expense` - 23 edges
6. `InvoiceController` - 22 edges
7. `CommercialDocumentController` - 20 edges
8. `Department` - 19 edges
9. `Division` - 18 edges
10. `ExpenseController` - 16 edges

## Surprising Connections (you probably didn't know these)
- `DashboardController` --inherits--> `Controller`  [EXTRACTED]
  Http/Controllers/Admin/DashboardController.php → Http/Controllers/Controller.php
- `RoleController` --inherits--> `Controller`  [EXTRACTED]
  Http/Controllers/Admin/RoleController.php → Http/Controllers/Controller.php
- `UserController` --inherits--> `Controller`  [EXTRACTED]
  Http/Controllers/Admin/UserController.php → Http/Controllers/Controller.php
- `AcceptInviteController` --inherits--> `Controller`  [EXTRACTED]
  Http/Controllers/Auth/AcceptInviteController.php → Http/Controllers/Controller.php
- `AuthenticatedSessionController` --inherits--> `Controller`  [EXTRACTED]
  Http/Controllers/Auth/AuthenticatedSessionController.php → Http/Controllers/Controller.php

## Import Cycles
- None detected.

## Communities (64 total, 10 thin omitted)

### Community 0 - "Customer Deal Flow"
Cohesion: 0.08
Nodes (26): Customer, NumberSequenceService, RedirectResponse, Request, Response, Customer, Deal, RedirectResponse (+18 more)

### Community 1 - "Inertia Middleware"
Cohesion: 0.08
Nodes (17): CarbonInterface, NumberSequenceService, RedirectResponse, Request, Response, Request, Middleware, HandleInertiaRequests (+9 more)

### Community 2 - "Dashboard Aggregates"
Cohesion: 0.15
Nodes (13): DashboardController, Expense, ExpenseController, Request, Response, User, BahtText, FileAttachmentManager (+5 more)

### Community 3 - "Product Catalog"
Cohesion: 0.13
Nodes (15): ProductController, Product, RedirectResponse, Request, Response, Activity, RedirectResponse, Request (+7 more)

### Community 4 - "User Roles"
Cohesion: 0.16
Nodes (13): RoleController, UserController, RedirectResponse, Request, Response, User, NotificationService, RedirectResponse (+5 more)

### Community 5 - "Org Structure"
Cohesion: 0.24
Nodes (8): Department, Division, Branch, NumberSequenceService, RedirectResponse, Request, Response, OrganizationStructureController

### Community 6 - "Commercial Documents"
Cohesion: 0.20
Nodes (12): BillingNote, CreditDebitNote, DeliveryOrder, CommercialDocumentController, HttpResponse, NumberSequenceService, RedirectResponse, Request (+4 more)

### Community 7 - "Task Workflow"
Cohesion: 0.19
Nodes (14): TaskController, NotificationService, Project, RedirectResponse, Request, Response, Task, User (+6 more)

### Community 8 - "Project Delivery"
Cohesion: 0.19
Nodes (14): ProjectController, Deal, NotificationService, NumberSequenceService, Project, RedirectResponse, Request, Response (+6 more)

### Community 9 - "Invoice Finance"
Cohesion: 0.19
Nodes (9): InvoiceController, BahtText, HttpResponse, Invoice, NumberSequenceService, RedirectResponse, Request, Response (+1 more)

### Community 10 - "Supplier Tax Reports"
Cohesion: 0.20
Nodes (10): SupplierController, TaxReportController, NumberSequenceService, RedirectResponse, Request, Response, Request, Response (+2 more)

### Community 11 - "Files Payments"
Cohesion: 0.21
Nodes (11): FileController, PaymentController, Request, StoredFile, StreamedResponse, FileAttachmentManager, Invoice, RedirectResponse (+3 more)

### Community 12 - "Inventory Receipts"
Cohesion: 0.15
Nodes (10): GoodsReceiptController, NumberSequenceService, PurchaseOrder, RedirectResponse, Request, Response, GoodsReceiptItem, BelongsTo (+2 more)

### Community 13 - "Notifications"
Cohesion: 0.11
Nodes (11): InAppNotification, NotificationEvent, NotificationPreference, BelongsTo, PurchaseOrderItem, Setting, Voucher, MorphTo (+3 more)

### Community 14 - "Branch Models"
Cohesion: 0.12
Nodes (11): Branch, BelongsTo, Division, BelongsTo, Product, StoredFile, HasMany, Supplier (+3 more)

### Community 15 - "Profile Auth"
Cohesion: 0.14
Nodes (12): Authenticatable, ProfileController, HasFactory, RedirectResponse, Request, Response, BelongsTo, BelongsToMany (+4 more)

### Community 16 - "Purchase Orders"
Cohesion: 0.23
Nodes (10): PurchaseOrderController, BahtText, HttpResponse, NotificationService, NumberSequenceService, PurchaseOrder, RedirectResponse, Request (+2 more)

### Community 17 - "Quotations"
Cohesion: 0.34
Nodes (6): QuotationController, NumberSequenceService, RedirectResponse, Request, Response, Quotation

### Community 18 - "Shared Models"
Cohesion: 0.13
Nodes (9): Model, Activity, BelongsTo, DeliveryOrderItem, Permission, BelongsToMany, PurchaseRequestItem, BelongsTo (+1 more)

### Community 19 - "Deal Model"
Cohesion: 0.24
Nodes (4): HasOne, Deal, BelongsTo, HasMany

### Community 20 - "Contacts"
Cohesion: 0.50
Nodes (5): Contact, Customer, RedirectResponse, Request, ContactController

### Community 21 - "Invoice Model"
Cohesion: 0.27
Nodes (3): Invoice, BelongsTo, HasMany

### Community 22 - "Audit Verify"
Cohesion: 0.27
Nodes (6): AuditLogController, VerifyEmailController, Controller, EmailVerificationRequest, Response, RedirectResponse

### Community 23 - "Login Request"
Cohesion: 0.27
Nodes (3): LoginRequest, FormRequest, ProfileUpdateRequest

### Community 24 - "Mail Templates"
Cohesion: 0.29
Nodes (7): Content, Envelope, ErpNotificationMail, Mailable, Queueable, SerializesModels, ShouldQueue

### Community 25 - "Project Model"
Cohesion: 0.31
Nodes (3): BelongsTo, HasMany, Project

### Community 26 - "Invite Accept"
Cohesion: 0.44
Nodes (5): AcceptInviteController, RedirectResponse, Request, Response, User

### Community 27 - "Login Session"
Cohesion: 0.36
Nodes (5): AuthenticatedSessionController, RedirectResponse, Request, Response, LoginRequest

### Community 28 - "Customer Model"
Cohesion: 0.33
Nodes (3): Customer, BelongsTo, HasMany

### Community 29 - "Payment Model"
Cohesion: 0.33
Nodes (3): Payment, BelongsTo, HasMany

### Community 30 - "PO Model"
Cohesion: 0.33
Nodes (3): BelongsTo, HasMany, PurchaseOrder

### Community 31 - "Quotation Model"
Cohesion: 0.33
Nodes (3): BelongsTo, HasMany, Quotation

### Community 32 - "Task Model"
Cohesion: 0.33
Nodes (3): BelongsTo, HasMany, Task

### Community 33 - "Registration"
Cohesion: 0.39
Nodes (5): RegisteredUserController, RedirectResponse, Request, Response, OrganizationProvisioner

### Community 35 - "Role Model"
Cohesion: 0.36
Nodes (3): BelongsTo, BelongsToMany, Role

### Community 36 - "Password Confirm"
Cohesion: 0.43
Nodes (4): ConfirmablePasswordController, RedirectResponse, Request, Response

### Community 37 - "New Password"
Cohesion: 0.48
Nodes (4): NewPasswordController, RedirectResponse, Request, Response

### Community 38 - "Password Reset"
Cohesion: 0.43
Nodes (4): PasswordResetLinkController, RedirectResponse, Request, Response

### Community 39 - "Notification Settings"
Cohesion: 0.48
Nodes (4): RedirectResponse, Request, Response, NotificationPreferenceController

### Community 40 - "Billing Model"
Cohesion: 0.38
Nodes (3): BillingNote, BelongsTo, HasMany

### Community 41 - "Contact Model"
Cohesion: 0.38
Nodes (3): Contact, BelongsTo, HasMany

### Community 42 - "CN DN Model"
Cohesion: 0.38
Nodes (3): CreditDebitNote, BelongsTo, HasMany

### Community 43 - "DO Model"
Cohesion: 0.38
Nodes (3): DeliveryOrder, BelongsTo, HasMany

### Community 44 - "GRN Model"
Cohesion: 0.38
Nodes (3): GoodsReceipt, BelongsTo, HasMany

### Community 45 - "PR Model"
Cohesion: 0.38
Nodes (3): BelongsTo, HasMany, PurchaseRequest

### Community 46 - "Invoice Policy"
Cohesion: 0.67
Nodes (3): InvoicePolicy, Invoice, User

### Community 47 - "Product Policy"
Cohesion: 0.67
Nodes (3): Product, User, ProductPolicy

### Community 48 - "Verify Prompt"
Cohesion: 0.53
Nodes (4): EmailVerificationPromptController, RedirectResponse, Request, Response

### Community 49 - "Permission Middleware"
Cohesion: 0.53
Nodes (4): Closure, Request, Response, EnsurePermission

### Community 54 - "Verify Notification"
Cohesion: 0.60
Nodes (3): EmailVerificationNotificationController, RedirectResponse, Request

### Community 55 - "Password Update"
Cohesion: 0.60
Nodes (3): PasswordController, RedirectResponse, Request

### Community 56 - "Sales Dashboard"
Cohesion: 0.60
Nodes (3): Request, Response, SalesDashboardController

### Community 60 - "Activity Policy"
Cohesion: 0.60
Nodes (3): ActivityPolicy, Activity, User

## Knowledge Gaps
- **4 isolated node(s):** `HttpResponse`, `HttpResponse`, `HttpResponse`, `HttpResponse`
  These have ≤1 connection - possible missing edges or undocumented components.
- **10 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Controller` connect `Audit Verify` to `Customer Deal Flow`, `Inertia Middleware`, `Dashboard Aggregates`, `Product Catalog`, `User Roles`, `Org Structure`, `Commercial Documents`, `Task Workflow`, `Project Delivery`, `Invoice Finance`, `Supplier Tax Reports`, `Files Payments`, `Inventory Receipts`, `Profile Auth`, `Purchase Orders`, `Quotations`, `Contacts`, `Invite Accept`, `Login Session`, `Registration`, `Password Confirm`, `New Password`, `Password Reset`, `Notification Settings`, `Verify Prompt`, `Verify Notification`, `Password Update`, `Sales Dashboard`?**
  _High betweenness centrality (0.362) - this node is a cross-community bridge._
- **Why does `AuditLog` connect `Product Catalog` to `Customer Deal Flow`, `Inertia Middleware`, `Dashboard Aggregates`, `User Roles`, `Org Structure`, `Commercial Documents`, `Task Workflow`, `Project Delivery`, `Invoice Finance`, `Files Payments`, `Notifications`, `Quotations`, `Shared Models`, `Contacts`, `Audit Verify`, `Invite Accept`, `Login Session`?**
  _High betweenness centrality (0.338) - this node is a cross-community bridge._
- **Why does `SalesAccess` connect `Customer Deal Flow` to `Project Delivery`, `Sales Dashboard`, `Product Catalog`, `Contacts`?**
  _High betweenness centrality (0.062) - this node is a cross-community bridge._
- **Are the 24 inferred relationships involving `AuditLog` (e.g. with `.index()` and `.__invoke()`) actually correct?**
  _`AuditLog` has 24 INFERRED edges - model-reasoned connections that need verification._
- **What connects `HttpResponse`, `HttpResponse`, `HttpResponse` to the rest of the system?**
  _4 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Customer Deal Flow` be split into smaller, more focused modules?**
  _Cohesion score 0.08135593220338982 - nodes in this community are weakly interconnected._
- **Should `Inertia Middleware` be split into smaller, more focused modules?**
  _Cohesion score 0.08484848484848485 - nodes in this community are weakly interconnected._