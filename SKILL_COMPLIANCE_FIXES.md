# دليل تصحيح مخالفات قواعد السكيل

> هذا الملف يجمع المخالفات التي ظهرت في مراجعة الباك إند مقابل سكيل `laravel-service-pattern` (`.claude/skills/laravel-service-pattern/`)، مع طريقة تصحيح كل واحدة. نمشي فيه بالترتيب، خطوة خطوة.
>
> **أساس العمل:** فرع `refactor/pr10e-larastan-9` (آخر فرع في سلسلة الـ refactor، PR #77). الأرقام والمسارات في هذا الملف مأخوذة منه بتاريخ 2026-11-22. على `main` الحالي الملفات مختلفة.
>
> هذا الملف منفصل عن [BITRIX24_IMPLEMENTATION_STEPS.md](BITRIX24_IMPLEMENTATION_STEPS.md) ولا يغيّر شيئاً فيه.

---

## كيف تستخدم هذا الملف (اقرأ هذا أولاً)

1. **خطوة واحدة في كل مرة.** نفّذ الخطوة، شغّل `composer check`، تأكد من "النتيجة"، ثم ضع ✅ في مربعها وانتقل للتالية.
2. **لا تبدأ أي خطوة بدون موافقة صاحب المشروع.** قبل كل خطوة: اشرح ما ستفعله في سطرين وانتظر "تمام".
3. **لا تعمل commit إلا إذا طُلب منك.** كل قسم أدناه يصلح أن يكون PR مستقلاً فوق السلسلة.
4. **حمّل السكيل قبل أي تعديل،** واقرأ الملف المناسب في `references/` قبل كتابة ذلك النوع من الكود (لا تكتب من الذاكرة).
5. **السلوك الخارجي لا يتغير.** نفس الـ routes، نفس شكل الـ JSON، نفس النتائج في Bitrix. الاختبارات الحالية (437) يجب أن تبقى ناجحة بعد كل خطوة.
6. **كل خطوة تضيف اختبار معمارية** يمنع رجوع المخالفة، إن أمكن.
7. **ملاحظة تشغيل محلية:** على هذا الجهاز `composer check` قد يفشل في Rector بسبب الذاكرة (العمليات المتوازية). عندها شغّل بالتسلسل:
   ```bash
   php -d memory_limit=1G vendor/bin/rector --dry-run --debug
   php vendor/bin/phpstan analyse --memory-limit=1G
   php vendor/bin/pest
   ```

---

## الصورة الكاملة باختصار

**الوضع الحالي:** Pint وRector وLarastan المستوى 9 كلها نظيفة، و437 اختباراً تنجح (منها 21 اختبار معمارية). طبقة الـ HTTP والأمان والـ DTOs ملتزمة. المخالفات المتبقية تتركز في طبقة Bitrix24 والـ Models والـ Observers، ولا تلتقطها اختبارات المعمارية لأنها لا تغطي هذه الأماكن.

| القسم | الخطورة | الخطوات |
|---|---|---|
| A: الأمان | 🚨 Critical | A.1 |
| B: حدود الطبقات (ARC) | 🔴 Blocker | B.1 – B.6 |
| C: الاعتماديات والـ Resources | 🟠 Major | C.1 – C.4 |
| D: الـ Queues والثوابت | 🟠 Major | D.1 – D.2 |
| E: التسمية والتفاصيل | 🟡 Minor | E.1 – E.3 |
| F: سد ثغرات اختبارات المعمارية | – | F.1 |
| G: انحرافات مقصودة تحتاج قراراً | – | G.1 – G.2 |

---

## حالة التنفيذ (2026-11-25)

**كل الخطوات من A.1 إلى F.1 تمت.** نُفّذت في السلسلة PR11، من #79 إلى #94. الـ PRs متراكبة (كل واحد مبني على الذي قبله)، فتُدمج بالترتيب، ولم يُدمج أي منها بعد. كل PR جُرّب على البوابة الحقيقية قبل الكوميت. وفي النهاية: `composer check` أخضر، وLarastan على المستوى 9 بلا baseline، و**551 اختبارًا** (كانت 437).

| PR | الخطوات |
|---|---|
| #79 PR11a | A.1 |
| #80 PR11b | G.1، G.2: قرارات صاحب المشروع، ووثّقناها في CLAUDE.md |
| #81 PR11c | B.1، C.4، E.2 |
| #82 PR11d-1، #83 PR11d-2 | B.3 (جزآن) |
| #84 PR11e-1، #85 PR11e-2 | B.2، C.3، واختبار وحدة لكل Action |
| #86 PR11f | B.4، D.2 |
| #87 PR11g | B.5، D.1 |
| #88 PR11h-1، #89 PR11h-2 | C.1 (جزآن) |
| #90 PR11i-1، #91 PR11i-2، #92 PR11i-3 | C.2 (ثلاثة أجزاء) |
| #93 PR11j | B.6، E.1، E.3 |
| #94 PR11k | F.1 |

### قرارات أثناء التنفيذ تختلف عن نص هذا الملف

كلها بموافقة صاحب المشروع، أو لأن النص المقترح لا يعمل كما هو:

- **D.1: تخطّي الـ job لا إعادته.** إذا وجد الـ job أن `WithoutOverlapping` مقفل، **يُتخطّى** ولا يُعاد (`dontRelease()`)، بدل `releaseAfter`. السبب أن `$tries = 1`، فإعادة الـ job كانت ستنتهي بفشله وبتسجيل خطأ على الـ cursor. والتخطّي آمن، لأن الاستيراد الجاري يعمل نفس العمل، وكل استيراد قابل للتكرار بلا ضرر. والقفل منفصل لكل كيان، ويُفك بعد `$timeout`.
- **D.2: عدّاد المهام قيد التنفيذ.** يستعمل `TaskStatus::IN_PROGRESS_KEY` بدل الحالات غير النهائية. لو استعملنا الحالات غير النهائية، لدخلت المهام المعلّقة في الرقم الظاهر على بطاقة المشروع.
- **C.1: حذف الحقول الداخلية من الردود.** هذا بموافقة صاحب المشروع. الغلاف (`success`، `message`، `data`، `meta`) بقي كما هو، ومعه كل حقل تقرؤه الواجهة. أما الحقول الداخلية التي لا تقرؤها الواجهة فلم تعد تخرج من الخادم، ومنها: موعد انتهاء توكن Bitrix، والأقسام، و`email_verified_at`، وصفوف `pivot`، و`deleted_at`. واختبارات الردود تثبت الحالتين: الحقول المقروءة موجودة، والداخلية غائبة. وحُوّل `PUT /me` إلى Resource أيضًا، مع أنه لم يكن في القائمة.
- **C.2: سجل النشاط يعرض الاسم.** سطر "أُضيف عضو" يكتب اسم العضو بدل `User ID n`، بطلب صاحب المشروع.
- **B.6: الـ service تقرر نتيجة التثبيت.** `BitrixInstallController` نُفّذ بالحل الكامل، باختيار صاحب المشروع: الـ service ترجع `BitrixInstallResultDTO`، والـ controller يحوّله إلى JSON أو صفحة فقط.
- **F.1: قاعدة الـ Resources تقرأ الكود.** الصيغة المقترحة `not->toUse('App\Models')` تفشل، لأن الـ controllers تستقبل الـ models في معاملاتها. لذلك صارت القاعدة تقرأ كود الـ controllers، وتمنع record خامًا تحت `data`/`user`/`role`/`project`/`task`، وتمنع `response()->json($قيمة)`. وأضفنا قواعد أخرى: الـ controllers في `Api\V1`، والـ DTOs المبنية من FormRequest فيها `fromArray()`، و`App\Enums` فيه enums فقط.

### انحرافات مقبولة باقية

- **QUE-6:** الكتابة إلى Bitrix24 تبقى داخل طلب الـ HTTP (G.1، وهي في CLAUDE.md).
- **ARC-1 جزئيًا:** جانب HTTP في OAuth يبقى في `BitrixAuthController`، أي بدء الـ state ومقارنة الـ host والتحويلات (G.2، وهي في CLAUDE.md). أما فحص الـ state فانتقل إلى `BitrixSignInRequest`.
- **ARC-15 (E.3، منخفض الأولوية):** `BitrixGatewayInterface` يأخذ الـ model `HasBitrixToken` سياقًا للطلب، بدل `BitrixContextDTO`.
- **ARC-9، اعتماديات مسموحة صراحةً:** قائمة مالكي الـ repositories في `ArchitectureTest` تسمح بثلاث حالات:
  - `UserService` يستعمل `BitrixPortalRepository`.
  - خدمات المشاريع والمهام تستعمل `BitrixOutboundOpRepository`، وهو سجل تغييراتها المرسلة إلى Bitrix.
  - الـ Actions والـ Integrations تستعمل الـ repositories مباشرة، لأن C.2 خاص بالـ services.
- **C.4:** `TracksSyncProgress::failed()` يستعمل `resolve()`، ومعه تعليق، لأن Laravel لا يحقن في `failed()`.

### ملاحظات للنشر

- **بعد الدمج:** شغّل `php artisan optimize:clear`، لأن الـ controllers انتقلت إلى `Api\V1`، ثم `php artisan queue:restart`.
- **اختبار متقطع:** في #92 فشل اختبار مرة واحدة ولم يتكرر في 14 تشغيلًا بعدها، ولم نعرف اسمه. ومن بعدها صار الكوميت لا يُنفَّذ إلا بعد نجاح `composer check` نفسه.

**الخطوة التالية بيد صاحب المشروع:** دمج السلسلة، وتحديد ما بعدها. المرحلة الخامسة من Bitrix24 لم تبدأ.

---

# القسم A: الأمان

### الخطوة A.1: المسؤول عن مهمة بدون مشروع يمكن أن يكون مستخدماً موقوفاً (SEC-2)

- [x] تمت: #79 (PR11a)

**المشكلة:** في [StoreTaskRequest.php:46](backend/app/Http/Requests/StoreTaskRequest.php#L46) الحقل `assigned_to` يُفحص بـ `exists:users,id` فقط. قاعدة `ProjectMember` ([ProjectMember.php:17](backend/app/Rules/ProjectMember.php#L17)) تنجح دائماً إذا لم يكن هناك مشروع. فالمهمة بدون مشروع (`GROUP_ID = 0`) تقبل أي مستخدم، حتى الموقوف. بقية طلبات المستخدمين (`AddProjectMemberRequest`، `DelegateTaskRequest`، `UpdateTaskMembersRequest`) تفلتر على `is_active` بشكل صحيح.

**الحل:**
- استبدل `'exists:users,id'` بـ `Rule::exists('users', 'id')->where('is_active', true)` في `StoreTaskRequest:46` و[UpdateTaskRequest.php:41](backend/app/Http/Requests/UpdateTaskRequest.php#L41) (نفس المشكلة).
- اختباران feature: إنشاء مهمة بدون مشروع مسندة لمستخدم موقوف، وتعديل مهمة لتُسند لمستخدم موقوف، كلاهما يرجع 422.

**النتيجة:** لا يمكن إسناد مهمة لمستخدم موقوف من أي طريق، والاختبار الجديد ينجح.

---

# القسم B: حدود الطبقات

### الخطوة B.1: TokenManager يستدعي `Http::` خارج الـ Gateway (ARC-15)

- [x] تمت: #81 (PR11c)

**المشكلة:** [TokenManager.php:86](backend/app/Integrations/Bitrix24/TokenManager.php#L86) يتصل بـ `oauth.bitrix.info` مباشرة عبر `Http::`. وهو class فعلي بلا interface، يُحقن في `BitrixGateway` وفي `BitrixAuthService:42`.

**الحل:**
- أنشئ `App\Integrations\Bitrix24\Contracts\BitrixOAuthGatewayInterface` بدالتين: `exchangeCode(string $code): BitrixTokenDTO` و`refresh(string $refreshToken): BitrixTokenDTO`.
- `BitrixOAuthGateway` هو الـ class **الوحيد** الذي يكلم خادم OAuth. ينقل إليه كود `Http::` من `TokenManager` كما هو (نفس معالجة الأخطاء ونفس `redactSecrets`).
- `TokenManager` يبقى مسؤولاً عن `Cache::lock` وتوقيت التجديد فقط، ويستقبل `BitrixOAuthGatewayInterface`.
- أنشئ `TokenManagerInterface` واحقنه بدل الـ class في `BitrixGateway` و`BitrixAuthService`.
- سجّل الربطين في `BindingServiceProvider::$bindings`.

**النتيجة:** `grep -rn "Http::" app` لا يرجع إلا `BitrixGateway.php` و`BitrixOAuthGateway.php`، واختبارات `BitrixGatewayTest` و`BitrixAuthTest` تنجح بدون تعديل في السلوك.

---

### الخطوة B.2: Transactions ومنطق أعمال داخل `Integrations/*Sync` (ARC-6، ARC-5)

- [x] تمت: #84 (PR11e-1)

**المشكلة:**
- [BitrixProjectSync.php:47](backend/app/Integrations/Bitrix24/BitrixProjectSync.php#L47) و[BitrixUserSync.php:44](backend/app/Integrations/Bitrix24/BitrixUserSync.php#L44) يفتحان `DB::transaction` بنفسيهما.
- كلاسات `Bitrix*Sync` (ProjectSync، UserSync، TaskSync، ChecklistSync، TimeSync، ChatSync، FileSync، ResultSync) تتصرف كـ Actions لكن فيها عدة دوال عامة، وتكتب في السجل، وتقرر قواعد (مثل الربط بالبريد).
- اختبار المعمارية يمنع `DB` داخل `App\Services` فقط، فلا يلتقط هذا.

**الحل:**
- الـ transaction تنتقل إلى الـ service التي تستدعي الـ Sync (`BitrixImportService`، `BitrixSyncService`، `BitrixAuthService`) عبر `ConnectionInterface` المحقون، كما في بقية الـ services.
- لكل Sync أحد خيارين (يُقرر لكل class على حدة):
  - **(أ) Action:** class واحد بدالة `handle()` عامة واحدة، داخل `app/Actions/Bitrix/`، بدون transaction وبدون اتصال خارجي مباشر (ARC-14). مناسب لـ `BitrixUserSync` و`BitrixProjectSync`.
  - **(ب) دمج في الـ service:** إذا كان الـ Sync يُستخدم من service واحدة فقط، تنتقل قواعده إليها، والكتابة إلى الـ repository.
- قاعدة "الربط بالبريد" (`$linkByEmail`) قاعدة أعمال، تبقى في الـ service.

**النتيجة:** لا يوجد `DB::` في `app/Integrations`، وكل transaction يملكها service، واختبارات الاستيراد والمزامنة تنجح.

---

### الخطوة B.3: Queries وكتابة داخل الـ Models (ARC-5)

- [x] تمت: #82 (PR11d-1)، #83 (PR11d-2)

**المشكلة:**
| المكان | ما فيه |
|---|---|
| [BitrixPortal.php:57](backend/app/Models/BitrixPortal.php#L57) | `current()` يعمل `static::where(...)->first()` |
| [BitrixPortal.php:83](backend/app/Models/BitrixPortal.php#L83)، [User.php:76](backend/app/Models/User.php#L76) | `storeBitrixToken()` يكتب ويعمل `->save()`، وتستدعيه `BitrixAuthService:148` و`BitrixInstallService:124` و`TokenManager:63` مباشرة |
| [Task.php:100](backend/app/Models/Task.php#L100)، [Task.php:260](backend/app/Models/Task.php#L260) | `DB::table('task_members')` داخل الـ model |
| [TaskStatus.php:37](backend/app/Models/TaskStatus.php#L37) | `finalKeys()` يعمل query ويخزنها في static |
| [Project.php:86](backend/app/Models/Project.php#L86) | `static::deleting` يحذف المهام واحدة واحدة (منطق أعمال داخل الـ model) |

**الحل:**
- `BitrixPortal::current()` ← موجود بالفعل في `BitrixPortalRepositoryInterface::current()`؛ احذف دالة الـ model وغيّر الاستدعاءات.
- `storeBitrixToken()` ← دالة `storeToken(HasBitrixToken $holder, BitrixTokenDTO $token)` في repository (`UserRepositoryInterface` و`BitrixPortalRepositoryInterface`). الـ model يبقى فيه فقط `bitrixAccessToken()` وما شابه للقراءة.
- `Task::isMember` و scope المهام المرئية ← دوال في `TaskRepositoryInterface` (مثل `hasMember(Task, User)`، `visibleTo(User)`) تستخدم العلاقة `members()` بدل `DB::table`.
- `TaskStatus::finalKeys()` ← `TaskLookupRepositoryInterface::finalStatusKeys()` مع cache داخل الـ repository، وكل من يستدعي `TaskStatus::finalKeys()` اليوم (ابحث بـ grep) ينتقل إليها.
- حذف مهام المشروع ← في `ProjectService::delete()` داخل الـ transaction، قبل حذف المشروع (تبقى سجلات النشاط لكل مهمة كما هي الآن).

**النتيجة:** لا `where`/`save`/`DB::` داخل `app/Models` (ما عدا العلاقات والـ casts والـ accessors)، والاختبارات تنجح.

---

### الخطوة B.4: الـ Observers تكتب في الـ repository مباشرة (ARC-12)

- [x] تمت: #86 (PR11f)

**المشكلة:** [TaskObserver.php:13](backend/app/Observers/TaskObserver.php#L13) و`ProjectObserver.php:13` يحقنان `ActivityLogRepositoryInterface`، يقرآن `Auth::id()`، ويبنيان مصفوفة السجل بأنفسهما. الـ Observer نقطة دخول مثل الـ Job، فيجب أن يستدعي service واحدة فقط.

**الحل:**
- أضف إلى `ActivityLogServiceInterface` دوالاً مثل `logTaskCreated(Task $task, ?int $actorId)`، `logTaskUpdated(...)`، `logTaskDeleted(...)`، ومثلها للمشروع. بناء الوصف والـ metadata ينتقل إلى الـ service.
- الـ Observer: سطر واحد في كل دالة، مثل `$this->activities->logTaskCreated($task, Auth::id())`.
- أسماء الأفعال (`task_created`...) تصبح enum (انظر D.2).
- اجعل الـ Observers `final`.

**النتيجة:** الـ Observers لا تعرف الـ repository، وسجلات النشاط تُكتب بنفس الشكل الحالي (اختبارات النشاط تنجح كما هي).

---

### الخطوة B.5: منطق داخل الأوامر (ARC-12)

- [x] تمت: #87 (PR11g)

**المشكلة:**
- [ImportFromBitrixCommand.php:89](backend/app/Console/Commands/ImportFromBitrixCommand.php#L89) يسجل `BitrixSyncCursor::saved(...)`، أي يستمع لحدث model مباشرة من داخل الأمر.
- [SyncBitrixChangesCommand.php:26-27](backend/app/Console/Commands/SyncBitrixChangesCommand.php#L26) فيه قرار أعمال ("إذا لم يُزامن من قبل، استورد كل شيء") ويستدعي أمراً آخر بـ `$this->call`.

**الحل:**
- **ImportFromBitrixCommand:** الـ service تستقبل callback للتقدم، مثل `run(array $entities, ?Closure $onProgress = null)`، وتستدعيه بعد كل صفحة. الأمر يمرر closure تحدّث الـ ProgressBar فقط.
- **SyncBitrixChangesCommand:** دالة واحدة في الـ service، مثل `syncChangesOrImportAll(): SyncResultDTO`، تقرر بنفسها. الأمر يطبع النتيجة فقط.

**النتيجة:** الأمران يقرآن الخيارات ويعرضان التقدم فقط، و`ImportFromBitrixCommandTest` ينجح.

---

### الخطوة B.6: منطق OAuth داخل BitrixAuthController وBitrixInstallController (ARC-1)

- [x] تمت: #93 (PR11j)

**المشكلة:** [BitrixAuthController.php:48-63](backend/app/Http/Controllers/BitrixAuthController.php#L48) و`:97-107` يقارنان الـ hosts، يحفظان `state` في الـ session، ويتحققان منه بـ `hash_equals`. و`BitrixInstallController.php:59-76` يختار بين رد الحدث وصفحة التثبيت. التعليق في السطر 29 يقول إن هذا مقصود ("keeps only the HTTP side").

**الحل (يحتاج قرار G.2 أولاً):**
- التحقق من `state` ينتقل إلى FormRequest (`BitrixCallbackRequest::authorize()` أو قاعدة تحقق)، لأنه تحقق إدخال.
- توليد الـ `state` ومقارنة الـ host ينتقلان إلى `BitrixAuthService`، والـ service تأخذ الـ session كـ interface (`Illuminate\Contracts\Session\Session`) لا كـ `Request`.
- `BitrixInstallController`: الـ service ترجع DTO يقول أي رد مطلوب، والـ controller يحوله إلى response فقط.

**النتيجة:** الـ controllers تستقبل FormRequest وتستدعي service واحدة وترجع رداً، واختبارات `BitrixAuthTest` (22) و`BitrixInstallTest` (16) تنجح بدون تغيير.

---

# القسم C: الاعتماديات والـ Resources

### الخطوة C.1: Controllers ترجع models خام بدل Resources (ARC-8)

- [x] تمت: #88 (PR11h-1)، #89 (PR11h-2)

**المشكلة:** `response()->json([...'data' => $model])` في: `ProjectController` (members، addMember، removeMember، toggleArchive، options، activities)، `TaskController:32`، `UserController`، `RoleController`، `UserRoleController`، `TaskStatusController`، `TaskPriorityController`، `PermissionController`. و`Controller::paginated()` ([Controller.php:19](backend/app/Http/Controllers/Controller.php#L19)) يرسل `$page->items()` خاماً. التوكنات لا تظهر فقط لأنها في `$hidden`.

**الحل:**
- لكل مورد Resource (موجود أو جديد): `RoleResource`، `PermissionResource`، `TaskStatusResource`، `TaskPriorityResource`، `ActivityLogResource`، `ProjectMemberResource`، `UserOptionResource`...
- `paginated()` يستقبل `class-string<JsonResource>` ويحول العناصر عبرها.
- **شكل الـ JSON لا يتغير** (`success`، `message`، `data`، `meta`)، لأن الواجهة تقرأه. اكتب أولاً اختبارات تثبت الشكل الحالي (characterization) ثم غيّر.

**النتيجة:** `grep "response()->json" app/Http/Controllers` لا يرجع إلا الحالات بلا بيانات (رد حذف، رد حدث Bitrix)، والواجهة تعمل بدون تعديل.

---

### الخطوة C.2: Services تستدعي repositories مجالات أخرى (ARC-9)

- [x] تمت: #90 (PR11i-1)، #91 (PR11i-2)، #92 (PR11i-3)

**المشكلة:** الـ service تستدعي repository مجالها، ومجالات أخرى عبر **service** ذلك المجال فقط. الحالات الحالية:
| Service | Repository من مجال آخر | البديل |
|---|---|---|
| `ProjectService` | `ActivityLogRepository`، `UserRepository` | `ActivityLogService`، `UserService` |
| `TaskService` | `UserRepository`، `TaskLookupRepository` | `UserService`، `TaskLookupService` |
| `TaskMemberService` | `UserRepository` | `UserService` |
| `AuthService` | `RoleRepository` | `RoleService` |
| `UserService` | `RoleRepository` | `RoleService` |
| `UserRoleService` | `PermissionRepository`، `RoleRepository` | `PermissionService`، `RoleService` |
| `BitrixAuthService` | `UserRepository` | `UserService` |
| `BitrixImportService`، `BitrixSyncService` | `ProjectRepository`، `TaskRepository` | `ProjectService`، `TaskService` (دوال استيراد مخصصة) |

ملاحظة: الـ `Bitrix*RepositoryInterface` الخاص بنفس المجال مسموح (مثلاً `TaskService` ← `BitrixTaskRepository`). وأجزاء المهمة (chat، checklist، time، files، results) تُعتبر نفس مجال المهمة، فاستدعاؤها `TaskRepository` مقبول.

**الحل:** أضف الدوال الناقصة في interfaces الـ services المعنية، واحقن الـ service بدل الـ repository. انتبه للحلقات (ARC-9): إذا احتاجت `UserService` شيئاً من `ProjectService` والعكس، استخرج الجزء المشترك.

**النتيجة:** لا service تحقن repository من مجال آخر، وقاعدة معمارية تمنع ذلك (F.1).

---

### الخطوة C.3: Services تعتمد على classes فعلية بدل interfaces (ARC-4)

- [x] تمت: #85 (PR11e-2)

**المشكلة:** `BitrixImportService:74-81`، `BitrixSyncService:26-31`، `BitrixAuthService:42-46`، و`TaskChatService`، `TaskChecklistService`، `TaskFileService`، `TaskHistoryService`، `TaskMemberService`، `TaskResultService`، `TaskTimeService` تحقن `Bitrix*Sync` و`Bitrix*Mapper` و`BitrixEchoGuard` و`BitrixDeletionGuard` و`BitrixLoginCodes` و`TokenManager` كـ classes فعلية.

**الحل:**
- ما يتحول إلى Action في B.2 يبقى class (الـ Actions مسموحة كاعتماد فعلي).
- الـ Mappers: دوال نقية بلا حالة. إما تُستدعى داخل الـ Bitrix repository (الأفضل: الـ repository يرجع DTO جاهزاً)، أو تبقى كما هي مع توثيق أنها helpers.
- `BitrixEchoGuard`، `BitrixDeletionGuard`، `BitrixLoginCodes`، `TokenManager`: interface في `Integrations/Bitrix24/Contracts` + ربط في `BindingServiceProvider`.

**النتيجة:** الـ services لا تحقن إلا interfaces وActions، ويمكن عمل mock لكل اعتماد في unit tests.

---

### الخطوة C.4: `app()` و`resolve()` خارج الـ Providers (ARC-4)

- [x] تمت: #81 (PR11c)

**المشكلة:**
- [BitrixGateway.php:263-267](backend/app/Integrations/Bitrix24/BitrixGateway.php#L263) يجلب حساب الخدمة بـ `app()->bound()` و`resolve()`.
- [TracksSyncProgress.php:24](backend/app/Jobs/Bitrix/TracksSyncProgress.php#L24) يستخدم `resolve()` داخل `failed()`.
- [ParentTask.php:26](backend/app/Rules/ParentTask.php#L26) يستخدم `resolve(TaskRepositoryInterface)` داخل قاعدة تحقق (وقاعدة تحقق لا يجب أن تعرف الـ repository).
- `BitrixEventRequest.php:20` و`TaskLookupRequest.php:39` يستخدمان `resolve()` داخل الـ FormRequest.

**الحل:**
- `BitrixGateway`: يحقن `BitrixPortalRepositoryInterface` في الـ constructor، ويجلب حساب الخدمة عند الحاجة. احذف ربط `SERVICE_ACCOUNT` من `AppServiceProvider:32-35` (يحل E.2 أيضاً).
- `TracksSyncProgress::failed()`: هذا الاستخدام في `failed()` مقبول في Laravel لأنه لا يمكن الحقن فيها؛ وثّقه بتعليق، أو استخدم `app()->call`. (قرار بسيط.)
- `ParentTask`: تستقبل `TaskServiceInterface` في الـ constructor من الـ FormRequest (عبر حقن في دالة `rules()`).
- الـ FormRequests: الحقن عبر توقيع دالة `authorize()`/`rules()` (Laravel يدعمه)، بدل `resolve()`.

**النتيجة:** `grep -rnE "\b(app|resolve)\(" app` لا يرجع إلا الـ Providers وحالات موثقة.

---

# القسم D: الـ Queues والثوابت

### الخطوة D.1: jobs الاستيراد بدون `$backoff` وبدون منع التداخل (QUE-1، QUE-5)

- [x] تمت: #87 (PR11g)

**المشكلة:** [TracksSyncProgress.php](backend/app/Jobs/Bitrix/TracksSyncProgress.php) (تستخدمه الـ jobs الثماني `Import*`) فيه `$tries = 1` و`$timeout = 3600`، بدون `$backoff`، وبدون `ShouldBeUnique` أو `WithoutOverlapping`. الجدولة الليلية في [routes/console.php:15](backend/routes/console.php#L15) فيها `->withoutOverlapping()`، لكنها تحمي **الأمر** فقط، والأمر ينتهي بسرعة بعد إرسال الـ jobs إلى الـ queue. فاستيراد يدوي عبر `bitrix:import` قد تعمل jobs منه مع jobs الاستيراد الليلي على نفس الـ cursor في نفس الوقت.

**الحل:**
- `public int $backoff = 0;` صراحة (لأن `$tries = 1`، مع تعليق يشرح أن الاستيراد يعاد كاملاً).
- `middleware()` ترجع `[new WithoutOverlapping(self::ENTITY)->releaseAfter(...)->expireAfter($this->timeout)]`.
- اختبار: إرسال نفس الـ job مرتين أثناء تشغيل الأولى لا يشغّل الثانية بالتوازي.

**النتيجة:** لا يعمل استيراد نفس الكيان مرتين في نفس الوقت، والاختبار ينجح.

---

### الخطوة D.2: نصوص ثابتة بدل enums (CODE-6)

- [x] تمت: #86 (PR11f)

**المشكلة:**
- أفعال العمليات الصادرة `'create'`/`'update'`/`'delete'` في `ProjectService.php:183,203,215`، `TaskService.php:130,154,166`، `TaskMemberService.php:110`، وثوابت الكيان في `BitrixOutboundOp` (`BitrixOutboundOp::PROJECT`، `::TASK`).
- أفعال سجل النشاط `'task_created'`، `'project_updated'`... في الـ Observers.
- حالة المهمة `'in_progress'` في `EloquentProjectRepository.php:164`.

**الحل:**
- `enum OutboundOpAction: string { case Create = 'create'; case Update = 'update'; case Delete = 'delete'; }` و`enum OutboundOpEntity: string`، مع cast على `BitrixOutboundOp`.
- `enum ActivityAction: string` مع cast على `ActivityLog`.
- `'in_progress'`: حالات المهام بيانات في جدول `task_statuses`، فلا enum؛ استخدم ثابتاً واحداً معرّفاً (مثل `TaskStatus::IN_PROGRESS_KEY`) أو اسأل `TaskLookupRepository` عن الحالات غير النهائية (`is_final = false`)، وهذا أصح لأنه يغطي الحالات المخصصة.
- **القيم المخزنة في قاعدة البيانات لا تتغير**، فلا حاجة لـ migration.

**النتيجة:** لا نصوص حالات أو أفعال مكتوبة يدوياً في الـ services والـ repositories.

---

# القسم E: التسمية والتفاصيل

### الخطوة E.1: `signInWithCode` يستقبل إدخالاً خاماً (ARC-3، CODE-4)

- [x] تمت: #93 (PR11j)

**المشكلة:** [BitrixAuthServiceInterface.php:18](backend/app/Services/Contracts/BitrixAuthServiceInterface.php#L18): `signInWithCode(string $code, mixed $domain, string $nonce)`. الـ `mixed $domain` قيمة query خام من [BitrixAuthController.php:106](backend/app/Http/Controllers/BitrixAuthController.php#L106).

**الحل:** `BitrixCallbackDTO` (code، domain كـ `?string`، nonce) يُبنى في `toDto()` على الـ FormRequest، والـ service تستقبله.

**النتيجة:** لا `mixed` في توقيعات الـ service interfaces.

---

### الخطوة E.2: ربط خارج `BindingServiceProvider` (CFG-2)

- [x] تمت: #81 (PR11c)

**المشكلة:** [AppServiceProvider.php:32-35](backend/app/Providers/AppServiceProvider.php#L32) يربط `BitrixGatewayInterface::SERVICE_ACCOUNT`.

**الحل:** يُحل مع C.4 (الـ gateway يجلب الحساب من الـ repository). إذا بقي الربط لسبب ما، ينتقل إلى `BindingServiceProvider`.

**النتيجة:** `AppServiceProvider::register()` فارغ أو لا ربط فيه.

---

### الخطوة E.3: تفاصيل أصغر

- [x] تمت: #86 (PR11f)، #93 (PR11j)، #94 (PR11k)

| المكان | القاعدة | التصحيح |
|---|---|---|
| `ProjectObserver`، `TaskObserver` | CODE-2 | `final` (مع B.4) |
| `BitrixFrameAuthDTO`، `BitrixInstallDTO` | CODE-3 | أضف `fromArray()`، وأبقِ `fromEvent`/`fromPage` كأسماء مساعدة تستدعيها |
| كل الـ controllers في `app/Http/Controllers` | التسمية | نقلها إلى `Api\V1\` (المسارات وأسماء `api.v1.*` موجودة بالفعل منذ PR7c، فالنقل namespace فقط) |
| [routes/web.php:13-14](backend/routes/web.php#L13) | التسمية | أسماء للمسارين: `auth.bitrix.redirect` و`auth.bitrix.callback` |
| `BitrixGatewayInterface.php:36+` | ARC-15 | سياق الطلب هو الـ model `HasBitrixToken` بدل `BitrixContextDTO`. **منخفض الأولوية**؛ وثّقه كانحراف مقبول أو حوّله مع B.1 |

**النتيجة:** لا ملاحظات Minor متبقية، أو كل واحدة موثقة كانحراف.

---

# القسم F: سد ثغرات اختبارات المعمارية

### الخطوة F.1: اختبارات معمارية تلتقط ما سبق

- [x] تمت: #94 (PR11k)، والقواعد مع كل PR أصلح مخالفتها

**المشكلة:** [tests/Arch/ArchitectureTest.php:120-122](backend/tests/Arch/ArchitectureTest.php#L120) يسمح بـ `Http` في كل `App\Integrations`، ولا توجد قواعد للـ Observers ولا الـ Models ولا ARC-9. لهذا مرت كل المخالفات أعلاه وكل الاختبارات خضراء.

**الحل:** أضف (بعد إصلاح الخطوات المعنية، وإلا ستفشل):
```php
arch('only the gateways talk to external HTTP')
    ->expect(['Illuminate\Support\Facades\Http', 'Illuminate\Http\Client\Factory'])
    ->toOnlyBeUsedIn([
        'App\Integrations\Bitrix24\BitrixGateway',
        'App\Integrations\Bitrix24\BitrixOAuthGateway',
        'Tests',
    ]);

arch('integrations do not own transactions')
    ->expect('App\Integrations')
    ->not->toUse(['Illuminate\Support\Facades\DB', 'Illuminate\Database\ConnectionInterface']);

arch('models do not query other tables')
    ->expect('App\Models')
    ->not->toUse('Illuminate\Support\Facades\DB');

arch('observers are thin')
    ->expect('App\Observers')
    ->toBeFinal()
    ->not->toUse(['App\Repositories', 'Illuminate\Support\Facades\DB']);

arch('controllers return resources')
    ->expect('App\Http\Controllers')
    ->not->toUse('App\Models'); // يُضبط بعد C.1 حسب ما يبقى مقبولاً
```
ولـ ARC-9: قاعدة لكل service تحدد الـ repositories المسموحة له (مثلاً `ProjectService` لا يستخدم `UserRepositoryInterface`).

**النتيجة:** إرجاع أي مخالفة من القسمين B وC يُفشل `composer check`.

---

# القسم G: انحرافات مقصودة تحتاج قراراً

هذه ليست أخطاء منسية: الكود يوثقها كخيار مقصود. تحتاج موافقة صاحب المشروع، إما **تُقبل وتُوثق** أو **تُصحح**.

### الخطوة G.1: الكتابة إلى Bitrix24 داخل طلب الـ HTTP (QUE-6)

- [ ] تم القرار

**الوضع:** حوالي 11 service (مثل `TaskService.php:126,144,165`، `TaskChatService.php:48`، `UserService.php:139`) تكتب إلى Bitrix24 مباشرة داخل الطلب، وبعض صفحات القراءة تحدّث من Bitrix بشكل متزامن. لا job يستخدم `RateLimited` أو `ThrottlesExceptions`. السبب موثق في [bootstrap/app.php:51-52](backend/bootstrap/app.php#L51): "التعديل الذي ترفضه Bitrix لا يُحفظ عندنا"، وهذا مطابق للخطوة 3.1 في خطة Bitrix.

**الخياران:**
- **(أ) قبول وتوثيق (مقترح):** يبقى التصميم. نضيف فقرة "انحرافات مقبولة" في `CLAUDE.md` تذكر QUE-6 وسببه، ونتأكد أن `BitrixGateway` فيه timeout قصير (`config('bitrix.timeout')`) حتى لا يعلق الطلب.
- **(ب) تصحيح:** الكتابة عبر queue. هذا يغير السلوك (الحفظ محلياً أولاً، ثم الإرسال، ثم معالجة الرفض لاحقاً) ويتعارض مع قرار الخطوة 3.1. **غير مقترح** الآن.

**ارتباط:** هذا القرار يحدد أيضاً شكل الخطوة 5.2 (dead-letter) في خطة Bitrix.

---

### الخطوة G.2: منطق OAuth في BitrixAuthController (ARC-1)

- [ ] تم القرار

**الوضع:** موثق في `BitrixAuthController.php:29`. إما يُقبل كانحراف (الـ session والـ redirects جزء من HTTP)، أو يُصحح كما في B.6. **المقترح:** تصحيح جزئي، أي نقل التحقق من `state` إلى FormRequest فقط (تحقق إدخال)، وترك الـ redirect في الـ controller.

---

## الترتيب المقترح للـ PRs

| PR | الخطوات | السبب |
|---|---|---|
| PR11a | A.1 | أمان، صغير، مستقل |
| PR11b | G.1، G.2 (قرارات + توثيق في CLAUDE.md) | تحدد شكل B.6 |
| PR11c | B.1، C.4، E.2 | كلها حول الـ gateway وحساب الخدمة |
| PR11d | B.3 | الـ models، يحتاجه B.2 |
| PR11e | B.2، C.3 | طبقة Sync والاعتماديات معاً |
| PR11f | B.4، D.2 | الـ observers والـ enums معاً |
| PR11g | B.5، D.1 | الأوامر والـ jobs |
| PR11h | C.1 | الـ Resources (مع characterization tests أولاً) |
| PR11i | C.2 | ARC-9 |
| PR11j | B.6، E.1، E.3 | الباقي |
| PR11k | F.1 | آخراً، بعد أن تصبح كل القواعد قابلة للنجاح |

كل PR: `composer check` أخضر، والاختبارات الحالية (437) ناجحة بدون تعديل في توقعاتها، إلا إذا وافق صاحب المشروع على تغيير.
