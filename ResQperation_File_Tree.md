# File Tree: resqperation-system

**Generated:** 6/5/2026, 9:16:52 PM
**Root Path:** `c:\backend\G10CAPSTONE\resqperation-system`

```
├── 📁 backend-laravel
│   ├── 📁 app
│   │   ├── 📁 Http
│   │   │   ├── 📁 Controllers
│   │   │   │   ├── 📁 Api
│   │   │   │   │   ├── 🐘 ArchiveController.php
│   │   │   │   │   ├── 🐘 AuthController.php
│   │   │   │   │   ├── 🐘 DashboardController.php
│   │   │   │   │   ├── 🐘 DisasterBroadcastController.php
│   │   │   │   │   ├── 🐘 HouseholdStatusController.php
│   │   │   │   │   ├── 🐘 MappingController.php
│   │   │   │   │   ├── 🐘 NotificationController.php
│   │   │   │   │   ├── 🐘 ProfileController.php
│   │   │   │   │   ├── 🐘 RescueDispatchController.php
│   │   │   │   │   ├── 🐘 RescuerAccountController.php
│   │   │   │   │   ├── 🐘 ResourceRequestController.php
│   │   │   │   │   ├── 🐘 SituationReportController.php
│   │   │   │   │   └── 🐘 WeatherController.php
│   │   │   │   └── 🐘 Controller.php
│   │   │   ├── 📁 Middleware
│   │   │   │   └── 🐘 EnsureUserHasRole.php
│   │   │   ├── 📁 Requests
│   │   │   │   └── 🐘 LoginRequest.php
│   │   │   └── 📁 Resources
│   │   │       └── 🐘 UserResource.php
│   │   ├── 📁 Models
│   │   │   ├── 🐘 Role.php
│   │   │   └── 🐘 User.php
│   │   ├── 📁 Providers
│   │   │   └── 🐘 AppServiceProvider.php
│   │   └── 📁 Services
│   │       ├── 🐘 BarangayProfileService.php
│   │       └── 🐘 WeatherSnapshotService.php
│   ├── 📁 bootstrap
│   │   ├── 🐘 app.php
│   │   └── 🐘 providers.php
│   ├── 📁 config
│   │   ├── 🐘 app.php
│   │   ├── 🐘 auth.php
│   │   ├── 🐘 cache.php
│   │   ├── 🐘 database.php
│   │   ├── 🐘 filesystems.php
│   │   ├── 🐘 logging.php
│   │   ├── 🐘 mail.php
│   │   ├── 🐘 queue.php
│   │   ├── 🐘 sanctum.php
│   │   ├── 🐘 services.php
│   │   └── 🐘 session.php
│   ├── 📁 database
│   │   ├── 📁 factories
│   │   │   └── 🐘 UserFactory.php
│   │   ├── 📁 migrations
│   │   │   ├── 🐘 0001_01_01_000000_create_users_table.php
│   │   │   ├── 🐘 0001_01_01_000001_create_cache_table.php
│   │   │   ├── 🐘 0001_01_01_000002_create_jobs_table.php
│   │   │   └── 🐘 2026_05_29_170622_create_personal_access_tokens_table.php
│   │   ├── 📁 seeders
│   │   │   ├── 🐘 DatabaseSeeder.php
│   │   │   ├── 🐘 SampleDisasterStatusSeeder.php
│   │   │   └── 🐘 TemporaryLoginSeeder.php
│   │   ├── ⚙️ .gitignore
│   │   └── 📄 database.sqlite
│   ├── 📁 public
│   │   ├── ⚙️ .htaccess
│   │   ├── 📄 favicon.ico
│   │   ├── 🐘 index.php
│   │   └── 📄 robots.txt
│   ├── 📁 resources
│   │   ├── 📁 css
│   │   │   └── 🎨 app.css
│   │   ├── 📁 js
│   │   │   └── 📄 app.js
│   │   └── 📁 views
│   │       └── 🐘 welcome.blade.php
│   ├── 📁 routes
│   │   ├── 🐘 api.php
│   │   ├── 🐘 console.php
│   │   └── 🐘 web.php
│   ├── 📁 storage
│   │   ├── 📁 app
│   │   │   ├── 📁 private
│   │   │   │   └── ⚙️ .gitignore
│   │   │   ├── 📁 public
│   │   │   │   └── ⚙️ .gitignore
│   │   │   └── ⚙️ .gitignore
│   │   └── 📁 framework
│   │       ├── 📁 sessions
│   │       │   └── ⚙️ .gitignore
│   │       ├── 📁 testing
│   │       │   └── ⚙️ .gitignore
│   │       ├── 📁 views
│   │       │   ├── ⚙️ .gitignore
│   │       │   └── 🐘 00fe67e04c091fb3cfbe76fcb8e2b270.php
│   │       └── ⚙️ .gitignore
│   ├── 📁 tests
│   │   ├── 📁 Feature
│   │   │   ├── 🐘 AuthApiTest.php
│   │   │   └── 🐘 ExampleTest.php
│   │   ├── 📁 Unit
│   │   │   └── 🐘 ExampleTest.php
│   │   └── 🐘 TestCase.php
│   ├── ⚙️ .editorconfig
│   ├── ⚙️ .gitattributes
│   ├── ⚙️ .gitignore
│   ├── ⚙️ .npmrc
│   ├── 📝 README.md
│   ├── 📄 artisan
│   ├── ⚙️ composer.json
│   ├── ⚙️ package.json
│   ├── ⚙️ phpunit.xml
│   └── 📄 vite.config.js
├── 📁 docs
│   ├── 📁 sql_proposals
│   │   ├── 📁 initial
│   │   │   ├── 📄 2026_06_03_g10_barangay_profile_review.sql
│   │   │   └── 📄 2026_06_03_g10_disaster_broadcast_metadata_review.sql
│   │   ├── 📄 2026_06_01_g10_all_sql_additive_update.sql
│   │   ├── 📄 2026_06_01_g10_existing_db_gap_review.sql
│   │   ├── 📄 2026_06_01_g10_resqperation_schema_review.sql
│   │   └── 📄 2026_06_02_g10_temporary_mobile_login_accounts.sql
│   ├── 📝 RESQPERATION_ACTUAL_DEVELOPMENT_PLAN.md
│   ├── 📝 RESQPERATION_BARANGAY_PROFILE_SCOPE.md
│   ├── 📝 RESQPERATION_G10_BEGINNER_MODULE_PROMPTS.md
│   ├── 📝 RESQPERATION_G10_DB_READINESS_CHECK.md
│   ├── 📝 RESQPERATION_G10_DEVELOPMENT_READINESS_AUDIT.md
│   ├── 📝 RESQPERATION_G10_SAFE_SAMPLE_SEED_PLAN.md
│   ├── 📝 RESQPERATION_G10_STEP_BY_STEP_CHECKLIST.md
│   ├── 📝 RESQPERATION_PAGASA_TENDAY_API_REQUEST_LETTER.md
│   ├── 📝 RESQPERATION_REQUIREMENTS_BREAKDOWN.md
│   ├── 📄 RESQPERATION_RESOURCES.txt
│   └── 📝 RESQPERATION_UPDATED_STEP_GUIDE.md
├── 📁 frontend-mobile
│   ├── 📁 api
│   │   ├── 📄 auth.ts
│   │   └── 📄 client.ts
│   ├── 📁 app
│   │   ├── 📁 (tabs)
│   │   │   ├── 📄 _layout.tsx
│   │   │   ├── 📄 explore.tsx
│   │   │   └── 📄 index.tsx
│   │   ├── 📁 household
│   │   │   ├── ⚙️ .gitkeep
│   │   │   └── 📄 index.tsx
│   │   ├── 📁 rescuer
│   │   │   ├── ⚙️ .gitkeep
│   │   │   └── 📄 index.tsx
│   │   ├── 📄 _layout.tsx
│   │   ├── 📄 index.tsx
│   │   └── 📄 modal.tsx
│   ├── 📁 assets
│   │   └── 📁 images
│   │       ├── 🖼️ android-icon-background.png
│   │       ├── 🖼️ android-icon-foreground.png
│   │       ├── 🖼️ android-icon-monochrome.png
│   │       ├── 🖼️ favicon.png
│   │       ├── 🖼️ icon.png
│   │       ├── 🖼️ partial-react-logo.png
│   │       ├── 🖼️ react-logo.png
│   │       ├── 🖼️ react-logo@2x.png
│   │       ├── 🖼️ react-logo@3x.png
│   │       └── 🖼️ splash-icon.png
│   ├── 📁 components
│   │   ├── 📁 ui
│   │   │   ├── 📄 collapsible.tsx
│   │   │   ├── 📄 icon-symbol.ios.tsx
│   │   │   └── 📄 icon-symbol.tsx
│   │   ├── 📄 external-link.tsx
│   │   ├── 📄 haptic-tab.tsx
│   │   ├── 📄 hello-wave.tsx
│   │   ├── 📄 parallax-scroll-view.tsx
│   │   ├── 📄 themed-text.tsx
│   │   └── 📄 themed-view.tsx
│   ├── 📁 constants
│   │   └── 📄 theme.ts
│   ├── 📁 hooks
│   │   ├── 📄 use-color-scheme.ts
│   │   ├── 📄 use-color-scheme.web.ts
│   │   └── 📄 use-theme-color.ts
│   ├── 📁 scripts
│   │   └── 📄 reset-project.js
│   ├── ⚙️ .gitignore
│   ├── 📝 README.md
│   ├── ⚙️ app.json
│   ├── 📄 eslint.config.js
│   ├── ⚙️ package-lock.json
│   ├── ⚙️ package.json
│   └── ⚙️ tsconfig.json
├── 📁 frontend-web
│   ├── 📁 public
│   │   ├── 🖼️ favicon.svg
│   │   └── 🖼️ icons.svg
│   ├── 📁 src
│   │   ├── 📁 api
│   │   │   ├── 📄 archiveApi.js
│   │   │   ├── 📄 authApi.js
│   │   │   ├── 📄 broadcastApi.js
│   │   │   ├── 📄 client.js
│   │   │   ├── 📄 dashboardApi.js
│   │   │   ├── 📄 dispatchApi.js
│   │   │   ├── 📄 householdApi.js
│   │   │   ├── 📄 mappingApi.js
│   │   │   ├── 📄 notificationApi.js
│   │   │   ├── 📄 profileApi.js
│   │   │   ├── 📄 rescuerApi.js
│   │   │   ├── 📄 resourceRequestApi.js
│   │   │   ├── 📄 situationReportApi.js
│   │   │   ├── 📄 token.js
│   │   │   └── 📄 weatherApi.js
│   │   ├── 📁 assets
│   │   │   ├── 🖼️ hero.png
│   │   │   ├── 🖼️ react.svg
│   │   │   └── 🖼️ vite.svg
│   │   ├── 📁 components
│   │   │   ├── 📁 archive
│   │   │   │   ├── 📄 ArchiveDownloadMenu.jsx
│   │   │   │   ├── 📄 ArchiveFilters.jsx
│   │   │   │   ├── 📄 ArchiveRecordModal.jsx
│   │   │   │   ├── 📄 ArchiveTable.jsx
│   │   │   │   └── 📄 ArchiveTabs.jsx
│   │   │   ├── 📁 broadcast
│   │   │   │   ├── 📄 BroadcastComposeForm.jsx
│   │   │   │   ├── 📄 BroadcastLifecycleCard.jsx
│   │   │   │   ├── 📄 BroadcastSidePanel.jsx
│   │   │   │   ├── 📄 BroadcastStartPanel.jsx
│   │   │   │   └── 📄 CloseActiveEventModal.jsx
│   │   │   ├── 📁 dashboard
│   │   │   │   ├── 📄 DashboardCloseEventModal.jsx
│   │   │   │   ├── 📄 DashboardMainContent.jsx
│   │   │   │   └── 📄 DashboardOverview.jsx
│   │   │   ├── 📁 dispatch
│   │   │   │   ├── 📄 DispatchModalForm.jsx
│   │   │   │   ├── 📄 DispatchSidePanel.jsx
│   │   │   │   ├── 📄 DispatchStatusBadge.jsx
│   │   │   │   ├── 📄 DispatchSummary.jsx
│   │   │   │   └── 📄 DispatchTeamGrid.jsx
│   │   │   ├── 📁 households
│   │   │   │   ├── 📄 HouseholdDetailContent.jsx
│   │   │   │   ├── 📄 HouseholdFilters.jsx
│   │   │   │   ├── 📄 HouseholdOpsPanels.jsx
│   │   │   │   ├── 📄 HouseholdSummary.jsx
│   │   │   │   └── 📄 HouseholdTable.jsx
│   │   │   ├── 📁 layout
│   │   │   │   ├── 📄 AppShell.jsx
│   │   │   │   ├── 📄 Sidebar.jsx
│   │   │   │   └── 📄 Topbar.jsx
│   │   │   ├── 📁 login
│   │   │   │   ├── 📄 LoginLanding.jsx
│   │   │   │   └── 📄 LoginModal.jsx
│   │   │   ├── 📁 mapping
│   │   │   │   ├── 📄 GeotagToolbar.jsx
│   │   │   │   ├── 📄 MappingEventStrip.jsx
│   │   │   │   ├── 📄 MappingMap.jsx
│   │   │   │   ├── 📄 MappingSidebar.jsx
│   │   │   │   └── 📄 MappingSummary.jsx
│   │   │   ├── 📁 notifications
│   │   │   │   ├── 📄 NotificationList.jsx
│   │   │   │   ├── 📄 NotificationSummary.jsx
│   │   │   │   └── 📄 NotificationToolbar.jsx
│   │   │   ├── 📁 profile
│   │   │   │   ├── 📄 PasswordModal.jsx
│   │   │   │   ├── 📄 ProfileActivityList.jsx
│   │   │   │   ├── 📄 ProfileEditModal.jsx
│   │   │   │   ├── 📄 ProfileIdentity.jsx
│   │   │   │   ├── 📄 ProfilePermissionList.jsx
│   │   │   │   └── 📄 ProfileSummary.jsx
│   │   │   ├── 📁 rescuers
│   │   │   │   ├── 📄 RescuerAccountModal.jsx
│   │   │   │   ├── 📄 RescuerFilters.jsx
│   │   │   │   ├── 📄 RescuerNotice.jsx
│   │   │   │   ├── 📄 RescuerRosterTable.jsx
│   │   │   │   ├── 📄 RescuerStats.jsx
│   │   │   │   └── 📄 RescuerTeamGrid.jsx
│   │   │   ├── 📁 resources
│   │   │   │   ├── 📄 ResourceRequestFilters.jsx
│   │   │   │   ├── 📄 ResourceRequestNotice.jsx
│   │   │   │   ├── 📄 ResourceRequestQueueTable.jsx
│   │   │   │   ├── 📄 ResourceRequestStats.jsx
│   │   │   │   ├── 📄 ResourceValidationModal.jsx
│   │   │   │   └── 📄 TrackingAidMirror.jsx
│   │   │   ├── 📁 situation
│   │   │   │   ├── 📄 SavedSitrepPanel.jsx
│   │   │   │   ├── 📄 SitrepGenerateModal.jsx
│   │   │   │   ├── 📄 SitrepPreview.jsx
│   │   │   │   ├── 📄 SituationActionMenu.jsx
│   │   │   │   └── 📄 SituationEventPanel.jsx
│   │   │   ├── 📁 ui
│   │   │   │   ├── 📄 ActionMenu.jsx
│   │   │   │   ├── 📄 Badge.jsx
│   │   │   │   ├── 📄 Button.jsx
│   │   │   │   ├── 📄 DataTable.jsx
│   │   │   │   ├── 📄 EmptyState.jsx
│   │   │   │   ├── 📄 FilterBar.jsx
│   │   │   │   ├── 📄 IconButton.jsx
│   │   │   │   ├── 📄 LoadingState.jsx
│   │   │   │   ├── 📄 Modal.jsx
│   │   │   │   ├── 📄 PageHeader.jsx
│   │   │   │   ├── 📄 Panel.jsx
│   │   │   │   ├── 📄 SearchInput.jsx
│   │   │   │   └── 📄 StatCard.jsx
│   │   │   └── 📁 weather
│   │   │       ├── 📄 WeatherLivePanel.jsx
│   │   │       ├── 📄 WeatherMainColumn.jsx
│   │   │       └── 📄 WeatherSidebar.jsx
│   │   ├── 📁 pages
│   │   │   ├── 📄 ArchivePage.jsx
│   │   │   ├── 📄 BroadcastPage.jsx
│   │   │   ├── 📄 DashboardPage.jsx
│   │   │   ├── 📄 HouseholdStatusPage.jsx
│   │   │   ├── 📄 LoginPage.jsx
│   │   │   ├── 📄 MappingPage.jsx
│   │   │   ├── 📄 NotificationsPage.jsx
│   │   │   ├── 📄 PlaceholderPage.jsx
│   │   │   ├── 📄 ProfilePage.jsx
│   │   │   ├── 📄 RescueDispatchPage.jsx
│   │   │   ├── 📄 RescuerAccountsPage.jsx
│   │   │   ├── 📄 ResourcesRequestsPage.jsx
│   │   │   ├── 📄 SituationReportPage.jsx
│   │   │   └── 📄 WeatherPage.jsx
│   │   ├── 📁 utils
│   │   │   ├── 📄 archiveHelpers.js
│   │   │   ├── 📄 broadcastHelpers.js
│   │   │   ├── 📄 dashboardHelpers.js
│   │   │   ├── 📄 dispatchHelpers.js
│   │   │   ├── 📄 householdStatusHelpers.js
│   │   │   ├── 📄 mappingHelpers.js
│   │   │   ├── 📄 notificationHelpers.js
│   │   │   ├── 📄 profileHelpers.js
│   │   │   ├── 📄 rescuerHelpers.js
│   │   │   ├── 📄 resourceRequestHelpers.js
│   │   │   ├── 📄 situationReportHelpers.js
│   │   │   └── 📄 weatherHelpers.js
│   │   ├── 🎨 App.css
│   │   ├── 📄 App.jsx
│   │   ├── 🎨 index.css
│   │   └── 📄 main.jsx
│   ├── ⚙️ .gitignore
│   ├── 📝 README.md
│   ├── 📄 eslint.config.js
│   ├── 🌐 index.html
│   ├── ⚙️ package-lock.json
│   ├── ⚙️ package.json
│   └── 📄 vite.config.js
├── ⚙️ .gitignore
└── 📝 README.md
```

---
*Generated by FileTree Pro Extension*