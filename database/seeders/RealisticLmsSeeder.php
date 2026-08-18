<?php

namespace Database\Seeders;

use App\Enums\CourseLevel;
use App\Enums\GradeLevel;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\ActivityLog;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Attendance;
use App\Models\Category;
use App\Models\Certificate;
use App\Models\CommunicationLog;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Group;
use App\Models\GroupSession;
use App\Models\Installment;
use App\Models\Lesson;
use App\Models\LoginActivity;
use App\Models\Payment;
use App\Models\Quiz;
use App\Models\QuizSubmission;
use App\Models\RevisionMaterial;
use App\Models\Resource;
use App\Models\StudentNote;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RealisticLmsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $users = $this->seedUsers();
            $categories = $this->seedCategories();
            $courses = $this->seedCourses($users, $categories);
            $sections = $this->seedSections($courses);
            $lessons = $this->seedLessons($sections);

            $this->seedResources($lessons);
            $this->seedRevisionMaterials($courses);

            $quizzes = $this->seedQuizzes($courses, $sections, $lessons);
            $this->seedQuizSubmissions($quizzes, $users['students']);

            $exams = $this->seedExams($courses);
            $this->seedExamAttempts($exams, $users['students']);

            $assignments = $this->seedAssignments($sections);
            $this->seedAssignmentSubmissions($assignments, $users['students'], $users['teachers']);

            $groups = $this->seedGroups($courses, $users['teachers']);
            $this->seedGroupMemberships($groups, $users['students']);
            $this->seedGroupSchedules($groups);

            $this->seedEnrollments($users['students'], $courses);
            $this->seedAttendances($groups, $users['students']);

            $this->seedParentLinks($users['parents'], $users['students']);
            $this->seedCommunicationLogs($users['parents'], $users['teachers']);
            $this->seedStudentNotes($users['students'], $users['teachers']);

            $this->seedPayments($users['students'], $courses, $users['admin']);
            $this->seedInstallments($users['students'], $courses);
            $this->seedCertificates($users['students'], $courses);

            $this->seedLoginActivities($users);
            $this->seedActivityLogs($users, $courses, $groups);
            $this->seedNotifications($users, $courses);
        });
    }

    /**
     * @return array{
     *     admin: User,
     *     teachers: array<string, User>,
     *     students: array<string, User>,
     *     parents: array<string, User>
     * }
     */
    private function seedUsers(): array
    {
        $definitions = [
            'admin' => [
                'name' => 'System Admin',
                'email' => 'admin@taalom.com',
                'password' => 'Admin@12345',
                'role' => UserRole::ADMIN,
                'phone' => '01000000000',
                'governorate' => 'Cairo',
            ],
            'teachers' => [
                'salma' => [
                    'name' => 'Salma Khaled',
                    'email' => 'salma.khaled@taalom.com',
                    'password' => 'Teacher@12345',
                    'phone' => '01000000001',
                    'governorate' => 'Giza',
                ],
                'ahmed' => [
                    'name' => 'Ahmed Mostafa',
                    'email' => 'ahmed.mostafa@taalom.com',
                    'password' => 'Teacher@12345',
                    'phone' => '01000000002',
                    'governorate' => 'Alexandria',
                ],
            ],
            'students' => [
                'yasmine' => [
                    'name' => 'Yasmine Hassan',
                    'email' => 'yasmine.hassan@taalom.com',
                    'password' => 'Student@12345',
                    'phone' => '01000000003',
                    'governorate' => 'Giza',
                    'grades' => GradeLevel::PREP_3->value,
                ],
                'mohamed' => [
                    'name' => 'Mohamed Adel',
                    'email' => 'mohamed.adel@taalom.com',
                    'password' => 'Student@12345',
                    'phone' => '01000000004',
                    'governorate' => 'Cairo',
                    'grades' => GradeLevel::PREP_2->value,
                ],
                'noha' => [
                    'name' => 'Noha Elshazly',
                    'email' => 'noha.elshazly@taalom.com',
                    'password' => 'Student@12345',
                    'phone' => '01000000005',
                    'governorate' => 'Sharqia',
                    'grades' => GradeLevel::SECONDARY_1->value,
                ],
                'omar' => [
                    'name' => 'Omar Farid',
                    'email' => 'omar.farid@taalom.com',
                    'password' => 'Student@12345',
                    'phone' => '01000000006',
                    'governorate' => 'Alexandria',
                    'grades' => GradeLevel::SECONDARY_3->value,
                ],
            ],
            'parents' => [
                'huda' => [
                    'name' => 'Huda Ali',
                    'email' => 'huda.ali@taalom.com',
                    'password' => 'Parent@12345',
                    'phone' => '01000000007',
                    'governorate' => 'Giza',
                ],
                'karim' => [
                    'name' => 'Karim Saeed',
                    'email' => 'karim.saeed@taalom.com',
                    'password' => 'Parent@12345',
                    'phone' => '01000000008',
                    'governorate' => 'Cairo',
                ],
            ],
        ];

        $createUser = static function (array $data, UserRole $role, ?string $grades = null): User {
            return User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => $data['password'],
                    'role' => $role,
                    'status' => UserStatus::ACTIVE,
                    'phone' => $data['phone'],
                    'governorate' => $data['governorate'] ?? null,
                    'grades' => $grades,
                    'email_verified_at' => Carbon::parse('2026-08-01 09:00:00'),
                    'last_login_at' => Carbon::parse('2026-08-14 19:30:00'),
                ]
            );
        };

        $admin = $createUser($definitions['admin'], UserRole::ADMIN);

        $teachers = [];
        foreach ($definitions['teachers'] as $key => $teacher) {
            $teachers[$key] = $createUser($teacher, UserRole::TEACHER);
        }

        $students = [];
        foreach ($definitions['students'] as $key => $student) {
            $students[$key] = $createUser($student, UserRole::STUDENT, $student['grades']);
        }

        $parents = [];
        foreach ($definitions['parents'] as $key => $parent) {
            $parents[$key] = $createUser($parent, UserRole::PARENT);
        }

        return [
            'admin' => $admin,
            'teachers' => $teachers,
            'students' => $students,
            'parents' => $parents,
        ];
    }

    /**
     * @return array<string, Category>
     */
    private function seedCategories(): array
    {
        $categories = [
            [
                'name' => 'Mathematics',
                'slug' => 'mathematics',
                'icon' => 'calculator',
                'description' => 'Prep and secondary math tracks with practice-heavy lessons.',
            ],
            [
                'name' => 'Physics',
                'slug' => 'physics',
                'icon' => 'atom',
                'description' => 'Concept-driven physics courses with problem solving and experiments.',
            ],
            [
                'name' => 'Arabic',
                'slug' => 'arabic',
                'icon' => 'book-open',
                'description' => 'Arabic grammar, reading, and writing support for school students.',
            ],
            [
                'name' => 'English',
                'slug' => 'english',
                'icon' => 'globe',
                'description' => 'English comprehension, writing, and exam preparation courses.',
            ],
            [
                'name' => 'Chemistry',
                'slug' => 'chemistry',
                'icon' => 'flask',
                'description' => 'Foundation chemistry lessons for secondary students.',
            ],
            [
                'name' => 'Biology',
                'slug' => 'biology',
                'icon' => 'leaf',
                'description' => 'Biology revision courses for life science topics.',
            ],
        ];

        $created = [];
        foreach ($categories as $category) {
            $created[$category['slug']] = Category::firstOrCreate(
                ['slug' => $category['slug']],
                $category + ['is_active' => true]
            );
        }

        return $created;
    }

    /**
     * @param array{admin: User, teachers: array<string, User>, students: array<string, User>, parents: array<string, User>} $users
     * @param array<string, Category> $categories
     *
     * @return array<string, Course>
     */
    private function seedCourses(array $users, array $categories): array
    {
        $blueprints = [
            [
                'slug' => 'algebra-foundations',
                'title' => 'أساسيات الجبر للصف الثالث الإعدادي',
                'teacher_key' => 'salma',
                'category_slug' => 'mathematics',
                'description' => 'Course focused on algebraic expressions, linear equations, and exam strategy.',
                'thumbnail' => 'courses/algebra-foundations.jpg',
                'price' => 850,
                'duration' => 24,
                'level' => CourseLevel::Beginner,
                'is_featured' => true,
                'sections' => [
                    [
                        'title' => 'الوحدة الأولى: التعبيرات الجبرية',
                        'description' => 'A practical introduction to variables, terms, and algebraic simplification.',
                        'lessons' => [
                            [
                                'title' => 'المتغيرات والتعابير',
                                'description' => 'Variables, constants, and building algebraic expressions.',
                                'video_url' => 'https://www.youtube.com/watch?v=algebra-variables',
                                'duration' => 42,
                                'is_preview' => true,
                            ],
                            [
                                'title' => 'تبسيط الحدود المتشابهة',
                                'description' => 'Combining like terms and common mistakes in simplification.',
                                'video_url' => 'https://www.youtube.com/watch?v=algebra-simplify',
                                'duration' => 38,
                                'is_preview' => false,
                            ],
                        ],
                    ],
                    [
                        'title' => 'الوحدة الثانية: المعادلات الخطية',
                        'description' => 'Solving one-step and two-step linear equations with confidence.',
                        'lessons' => [
                            [
                                'title' => 'حل المعادلات من خطوة واحدة',
                                'description' => 'Inverse operations and balancing both sides.',
                                'video_url' => 'https://www.youtube.com/watch?v=algebra-equations-1',
                                'duration' => 40,
                                'is_preview' => false,
                            ],
                            [
                                'title' => 'مسائل تطبيقية على المعادلات',
                                'description' => 'Word problems translated into equations and solved step by step.',
                                'video_url' => 'https://www.youtube.com/watch?v=algebra-equations-2',
                                'duration' => 45,
                                'is_preview' => false,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'physics-motion-waves',
                'title' => 'الفيزياء: الحركة والموجات',
                'teacher_key' => 'ahmed',
                'category_slug' => 'physics',
                'description' => 'Motion graphs, forces, waves, and exam-style practice for secondary students.',
                'thumbnail' => 'courses/physics-motion-waves.jpg',
                'price' => 920,
                'duration' => 28,
                'level' => CourseLevel::Intermediate,
                'is_featured' => true,
                'sections' => [
                    [
                        'title' => 'الحركة والقوة',
                        'description' => 'Speed, velocity, acceleration, and Newtonian intuition.',
                        'lessons' => [
                            [
                                'title' => 'السرعة والسرعة المتجهة',
                                'description' => 'Reading motion data and interpreting graphs.',
                                'video_url' => 'https://www.youtube.com/watch?v=physics-speed',
                                'duration' => 39,
                                'is_preview' => true,
                            ],
                            [
                                'title' => 'القوانين الأساسية للحركة',
                                'description' => 'Forces, mass, and acceleration examples.',
                                'video_url' => 'https://www.youtube.com/watch?v=physics-forces',
                                'duration' => 44,
                                'is_preview' => false,
                            ],
                        ],
                    ],
                    [
                        'title' => 'الموجات والصوت',
                        'description' => 'Wave properties, frequency, amplitude, and sound examples.',
                        'lessons' => [
                            [
                                'title' => 'خصائص الموجة',
                                'description' => 'Amplitude, wavelength, and frequency made simple.',
                                'video_url' => 'https://www.youtube.com/watch?v=physics-waves',
                                'duration' => 37,
                                'is_preview' => false,
                            ],
                            [
                                'title' => 'تطبيقات على الصوت',
                                'description' => 'Sound propagation, reflection, and everyday applications.',
                                'video_url' => 'https://www.youtube.com/watch?v=physics-sound',
                                'duration' => 33,
                                'is_preview' => false,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'arabic-grammar-writing',
                'title' => 'النحو والكتابة العربية',
                'teacher_key' => 'salma',
                'category_slug' => 'arabic',
                'description' => 'Arabic grammar foundations, sentence analysis, and writing improvement.',
                'thumbnail' => 'courses/arabic-grammar-writing.jpg',
                'price' => 780,
                'duration' => 22,
                'level' => CourseLevel::Beginner,
                'is_featured' => false,
                'sections' => [
                    [
                        'title' => 'أساسيات الإعراب',
                        'description' => 'A clear walkthrough of subject, predicate, and verb phrases.',
                        'lessons' => [
                            [
                                'title' => 'المبتدأ والخبر',
                                'description' => 'Understanding nominal sentences and core rules.',
                                'video_url' => 'https://www.youtube.com/watch?v=arabic-grammar',
                                'duration' => 41,
                                'is_preview' => true,
                            ],
                            [
                                'title' => 'الفاعل والمفعول به',
                                'description' => 'Sentence roles with practical examples.',
                                'video_url' => 'https://www.youtube.com/watch?v=arabic-subject-object',
                                'duration' => 36,
                                'is_preview' => false,
                            ],
                        ],
                    ],
                    [
                        'title' => 'التعبير والكتابة',
                        'description' => 'Paragraph structure, linking ideas, and clear Arabic writing.',
                        'lessons' => [
                            [
                                'title' => 'كتابة الفقرة',
                                'description' => 'How to plan, draft, and revise a strong paragraph.',
                                'video_url' => 'https://www.youtube.com/watch?v=arabic-paragraph',
                                'duration' => 34,
                                'is_preview' => false,
                            ],
                            [
                                'title' => 'أخطاء شائعة في الكتابة',
                                'description' => 'Avoiding spelling and punctuation mistakes.',
                                'video_url' => 'https://www.youtube.com/watch?v=arabic-writing-errors',
                                'duration' => 29,
                                'is_preview' => false,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'english-reading-writing',
                'title' => 'اللغة الإنجليزية: القراءة والكتابة',
                'teacher_key' => 'ahmed',
                'category_slug' => 'english',
                'description' => 'Reading comprehension, essay writing, and grammar support for school exams.',
                'thumbnail' => 'courses/english-reading-writing.jpg',
                'price' => 810,
                'duration' => 23,
                'level' => CourseLevel::Intermediate,
                'is_featured' => false,
                'sections' => [
                    [
                        'title' => 'Reading Skills',
                        'description' => 'Skimming, scanning, and answering comprehension questions.',
                        'lessons' => [
                            [
                                'title' => 'Reading a short passage',
                                'description' => 'How to identify main ideas and details quickly.',
                                'video_url' => 'https://www.youtube.com/watch?v=english-reading',
                                'duration' => 35,
                                'is_preview' => true,
                            ],
                            [
                                'title' => 'Vocabulary in context',
                                'description' => 'Learning words from clues in the passage.',
                                'video_url' => 'https://www.youtube.com/watch?v=english-vocabulary',
                                'duration' => 31,
                                'is_preview' => false,
                            ],
                        ],
                    ],
                    [
                        'title' => 'Writing Skills',
                        'description' => 'Paragraph and essay organization with practical models.',
                        'lessons' => [
                            [
                                'title' => 'Writing a paragraph',
                                'description' => 'Topic sentence, supporting details, and conclusion.',
                                'video_url' => 'https://www.youtube.com/watch?v=english-paragraph',
                                'duration' => 37,
                                'is_preview' => false,
                            ],
                            [
                                'title' => 'Formal and informal email',
                                'description' => 'Useful structures for school-level writing tasks.',
                                'video_url' => 'https://www.youtube.com/watch?v=english-email',
                                'duration' => 32,
                                'is_preview' => false,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'chemistry-basics',
                'title' => 'أساسيات الكيمياء',
                'teacher_key' => 'salma',
                'category_slug' => 'chemistry',
                'description' => 'Atoms, periodic trends, reactions, and a strong base for secondary chemistry.',
                'thumbnail' => 'courses/chemistry-basics.jpg',
                'price' => 950,
                'duration' => 26,
                'level' => CourseLevel::Advanced,
                'is_featured' => true,
                'sections' => [
                    [
                        'title' => 'الذرة والعناصر',
                        'description' => 'Atomic structure, elements, and how to read the periodic table.',
                        'lessons' => [
                            [
                                'title' => 'مكونات الذرة',
                                'description' => 'Protons, neutrons, electrons, and atomic number.',
                                'video_url' => 'https://www.youtube.com/watch?v=chemistry-atom',
                                'duration' => 41,
                                'is_preview' => true,
                            ],
                            [
                                'title' => 'الجدول الدوري',
                                'description' => 'Trends and organization of the periodic table.',
                                'video_url' => 'https://www.youtube.com/watch?v=chemistry-periodic-table',
                                'duration' => 39,
                                'is_preview' => false,
                            ],
                        ],
                    ],
                    [
                        'title' => 'التفاعلات الكيميائية',
                        'description' => 'How to balance equations and interpret reaction types.',
                        'lessons' => [
                            [
                                'title' => 'أنواع التفاعلات',
                                'description' => 'Synthesis, decomposition, single replacement, and more.',
                                'video_url' => 'https://www.youtube.com/watch?v=chemistry-reactions',
                                'duration' => 36,
                                'is_preview' => false,
                            ],
                            [
                                'title' => 'موازنة المعادلات',
                                'description' => 'Balancing chemical equations step by step.',
                                'video_url' => 'https://www.youtube.com/watch?v=chemistry-balance',
                                'duration' => 43,
                                'is_preview' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $created = [];

        foreach ($blueprints as $course) {
            $created[$course['slug']] = Course::firstOrCreate(
                ['slug' => $course['slug']],
                [
                    'teacher_id' => $users['teachers'][$course['teacher_key']]->id,
                    'category_id' => $categories[$course['category_slug']]->id,
                    'title' => $course['title'],
                    'description' => $course['description'],
                    'thumbnail' => $course['thumbnail'],
                    'price' => $course['price'],
                    'currency' => 'EGP',
                    'duration' => $course['duration'],
                    'level' => $course['level']->value,
                    'is_featured' => $course['is_featured'],
                    'is_published' => true,
                ]
            );
        }

        return $created;
    }

    /**
     * @param array<string, Course> $courses
     *
     * @return array<string, CourseSection>
     */
    private function seedSections(array $courses): array
    {
        $definitions = [
            'algebra-foundations' => [
                ['title' => 'الوحدة الأولى: التعبيرات الجبرية', 'description' => 'Variables, terms, and simplification.', 'order' => 1, 'is_published' => true],
                ['title' => 'الوحدة الثانية: المعادلات الخطية', 'description' => 'One-step and two-step equations.', 'order' => 2, 'is_published' => true],
            ],
            'physics-motion-waves' => [
                ['title' => 'الحركة والقوة', 'description' => 'Motion, force, and graph reading.', 'order' => 1, 'is_published' => true],
                ['title' => 'الموجات والصوت', 'description' => 'Wave behavior and sound propagation.', 'order' => 2, 'is_published' => true],
            ],
            'arabic-grammar-writing' => [
                ['title' => 'أساسيات الإعراب', 'description' => 'Sentence roles and grammar roots.', 'order' => 1, 'is_published' => true],
                ['title' => 'التعبير والكتابة', 'description' => 'Paragraph writing and correction.', 'order' => 2, 'is_published' => true],
            ],
            'english-reading-writing' => [
                ['title' => 'Reading Skills', 'description' => 'Comprehension and vocabulary.', 'order' => 1, 'is_published' => true],
                ['title' => 'Writing Skills', 'description' => 'Paragraphs and emails.', 'order' => 2, 'is_published' => true],
            ],
            'chemistry-basics' => [
                ['title' => 'الذرة والعناصر', 'description' => 'Atomic structure and the periodic table.', 'order' => 1, 'is_published' => true],
                ['title' => 'التفاعلات الكيميائية', 'description' => 'Balancing and reaction types.', 'order' => 2, 'is_published' => true],
            ],
        ];

        $created = [];

        foreach ($definitions as $courseSlug => $sections) {
            foreach ($sections as $section) {
                $model = CourseSection::firstOrCreate(
                    [
                        'course_id' => $courses[$courseSlug]->id,
                        'title' => $section['title'],
                    ],
                    [
                        'description' => $section['description'],
                        'order' => $section['order'],
                        'is_published' => $section['is_published'],
                    ]
                );

                $created[$courseSlug][$section['order']] = $model;
            }
        }

        return $created;
    }

    /**
     * @param array<string, array<int, CourseSection>> $sections
     *
     * @return array<string, Lesson>
     */
    private function seedLessons(array $sections): array
    {
        $lessonDefinitions = [
            'algebra-foundations' => [
                1 => [
                    ['title' => 'المتغيرات والتعابير', 'duration' => 42, 'is_preview' => true],
                    ['title' => 'تبسيط الحدود المتشابهة', 'duration' => 38, 'is_preview' => false],
                ],
                2 => [
                    ['title' => 'حل المعادلات من خطوة واحدة', 'duration' => 40, 'is_preview' => false],
                    ['title' => 'مسائل تطبيقية على المعادلات', 'duration' => 45, 'is_preview' => false],
                ],
            ],
            'physics-motion-waves' => [
                1 => [
                    ['title' => 'السرعة والسرعة المتجهة', 'duration' => 39, 'is_preview' => true],
                    ['title' => 'القوانين الأساسية للحركة', 'duration' => 44, 'is_preview' => false],
                ],
                2 => [
                    ['title' => 'خصائص الموجة', 'duration' => 37, 'is_preview' => false],
                    ['title' => 'تطبيقات على الصوت', 'duration' => 33, 'is_preview' => false],
                ],
            ],
            'arabic-grammar-writing' => [
                1 => [
                    ['title' => 'المبتدأ والخبر', 'duration' => 41, 'is_preview' => true],
                    ['title' => 'الفاعل والمفعول به', 'duration' => 36, 'is_preview' => false],
                ],
                2 => [
                    ['title' => 'كتابة الفقرة', 'duration' => 34, 'is_preview' => false],
                    ['title' => 'أخطاء شائعة في الكتابة', 'duration' => 29, 'is_preview' => false],
                ],
            ],
            'english-reading-writing' => [
                1 => [
                    ['title' => 'Reading a short passage', 'duration' => 35, 'is_preview' => true],
                    ['title' => 'Vocabulary in context', 'duration' => 31, 'is_preview' => false],
                ],
                2 => [
                    ['title' => 'Writing a paragraph', 'duration' => 37, 'is_preview' => false],
                    ['title' => 'Formal and informal email', 'duration' => 32, 'is_preview' => false],
                ],
            ],
            'chemistry-basics' => [
                1 => [
                    ['title' => 'مكونات الذرة', 'duration' => 41, 'is_preview' => true],
                    ['title' => 'الجدول الدوري', 'duration' => 39, 'is_preview' => false],
                ],
                2 => [
                    ['title' => 'أنواع التفاعلات', 'duration' => 36, 'is_preview' => false],
                    ['title' => 'موازنة المعادلات', 'duration' => 43, 'is_preview' => false],
                ],
            ],
        ];

        $created = [];
        $videoUrls = [
            'المتغيرات والتعابير' => 'https://www.youtube.com/watch?v=algebra-variables',
            'تبسيط الحدود المتشابهة' => 'https://www.youtube.com/watch?v=algebra-simplify',
            'حل المعادلات من خطوة واحدة' => 'https://www.youtube.com/watch?v=algebra-equations-1',
            'مسائل تطبيقية على المعادلات' => 'https://www.youtube.com/watch?v=algebra-equations-2',
            'السرعة والسرعة المتجهة' => 'https://www.youtube.com/watch?v=physics-speed',
            'القوانين الأساسية للحركة' => 'https://www.youtube.com/watch?v=physics-forces',
            'خصائص الموجة' => 'https://www.youtube.com/watch?v=physics-waves',
            'تطبيقات على الصوت' => 'https://www.youtube.com/watch?v=physics-sound',
            'المبتدأ والخبر' => 'https://www.youtube.com/watch?v=arabic-grammar',
            'الفاعل والمفعول به' => 'https://www.youtube.com/watch?v=arabic-subject-object',
            'كتابة الفقرة' => 'https://www.youtube.com/watch?v=arabic-paragraph',
            'أخطاء شائعة في الكتابة' => 'https://www.youtube.com/watch?v=arabic-writing-errors',
            'Reading a short passage' => 'https://www.youtube.com/watch?v=english-reading',
            'Vocabulary in context' => 'https://www.youtube.com/watch?v=english-vocabulary',
            'Writing a paragraph' => 'https://www.youtube.com/watch?v=english-paragraph',
            'Formal and informal email' => 'https://www.youtube.com/watch?v=english-email',
            'مكونات الذرة' => 'https://www.youtube.com/watch?v=chemistry-atom',
            'الجدول الدوري' => 'https://www.youtube.com/watch?v=chemistry-periodic-table',
            'أنواع التفاعلات' => 'https://www.youtube.com/watch?v=chemistry-reactions',
            'موازنة المعادلات' => 'https://www.youtube.com/watch?v=chemistry-balance',
        ];

        foreach ($lessonDefinitions as $courseSlug => $sectionLessons) {
            foreach ($sectionLessons as $sectionOrder => $lessons) {
                $section = $sections[$courseSlug][$sectionOrder];

                foreach ($lessons as $index => $lesson) {
                    $created[$courseSlug][$sectionOrder][$index + 1] = Lesson::firstOrCreate(
                        [
                            'course_section_id' => $section->id,
                            'title' => $lesson['title'],
                        ],
                        [
                            'description' => $this->lessonDescription($lesson['title']),
                            'video_url' => $videoUrls[$lesson['title']] ?? null,
                            'duration' => $lesson['duration'],
                            'order' => $index + 1,
                            'is_preview' => $lesson['is_preview'],
                        ]
                    );
                }
            }
        }

        return $created;
    }

    /**
     * @param array<string, array<int, array<int, Lesson>>> $lessons
     */
    private function seedResources(array $lessons): void
    {
        foreach ($lessons as $courseLessons) {
            foreach ($courseLessons as $sectionLessons) {
                foreach ($sectionLessons as $lesson) {
                    Resource::firstOrCreate(
                        [
                            'lesson_id' => $lesson->id,
                            'title' => $lesson->title . ' - ملف الشرح',
                        ],
                        [
                            'file_path' => 'resources/' . Str::slug($lesson->title) . '.pdf',
                            'file_type' => 'pdf',
                            'file_size' => 245000,
                        ]
                    );
                }
            }
        }
    }

    /**
     * @param array<string, Course> $courses
     */
    private function seedRevisionMaterials(array $courses): void
    {
        $files = [
            'algebra-foundations' => ['title' => 'مراجعة نهائية للجبر', 'file_path' => 'revision/algebra-final-review.pdf', 'file_size' => 510000],
            'physics-motion-waves' => ['title' => 'ملخص الفيزياء: الحركة والموجات', 'file_path' => 'revision/physics-motion-waves.pdf', 'file_size' => 540000],
            'arabic-grammar-writing' => ['title' => 'دفتر مراجعة النحو', 'file_path' => 'revision/arabic-grammar-review.pdf', 'file_size' => 430000],
            'english-reading-writing' => ['title' => 'English Writing Revision Pack', 'file_path' => 'revision/english-writing-pack.pdf', 'file_size' => 460000],
            'chemistry-basics' => ['title' => 'مراجعة الكيمياء الأساسية', 'file_path' => 'revision/chemistry-basics.pdf', 'file_size' => 500000],
        ];

        foreach ($files as $courseSlug => $material) {
            RevisionMaterial::firstOrCreate(
                [
                    'course_id' => $courses[$courseSlug]->id,
                    'title' => $material['title'],
                ],
                [
                    'file_path' => $material['file_path'],
                    'file_size' => $material['file_size'],
                ]
            );
        }
    }

    /**
     * @param array<string, Course> $courses
     * @param array<string, array<int, CourseSection>> $sections
     * @param array<string, array<int, array<int, Lesson>>> $lessons
     *
     * @return array<string, Quiz>
     */
    private function seedQuizzes(array $courses, array $sections, array $lessons): array
    {
        $blueprints = [
            'algebra-foundations' => [
                'section' => 1,
                'lesson' => 1,
                'title' => 'اختبار الوحدة الأولى - الجبر',
                'passing_score' => 60,
                'time_limit' => 20,
                'max_attempts' => 2,
                'questions' => [
                    [
                        'question' => 'ما قيمة 3x عندما x = 4؟',
                        'choices' => ['7', '12', '16', '24'],
                        'answer' => '12',
                    ],
                    [
                        'question' => 'أي عبارة تمثل حدًا جبريًا؟',
                        'choices' => ['5x', '5 + x = 10', 'x > 3', '12/4'],
                        'answer' => '5x',
                    ],
                ],
            ],
            'physics-motion-waves' => [
                'section' => 1,
                'lesson' => 1,
                'title' => 'Quiz 1: Motion Basics',
                'passing_score' => 55,
                'time_limit' => 18,
                'max_attempts' => 2,
                'questions' => [
                    [
                        'question' => 'What is acceleration?',
                        'choices' => ['Distance over time', 'Change in velocity', 'Force times mass', 'Sound frequency'],
                        'answer' => 'Change in velocity',
                    ],
                    [
                        'question' => 'Which unit measures speed?',
                        'choices' => ['meter/second', 'newton', 'joule', 'ampere'],
                        'answer' => 'meter/second',
                    ],
                ],
            ],
            'arabic-grammar-writing' => [
                'section' => 1,
                'lesson' => 1,
                'title' => 'اختبار النحو الأول',
                'passing_score' => 60,
                'time_limit' => 15,
                'max_attempts' => 2,
                'questions' => [
                    [
                        'question' => 'ما موقع "الطالب" في جملة: الطالبُ مجتهدٌ؟',
                        'choices' => ['مبتدأ', 'خبر', 'فاعل', 'مفعول به'],
                        'answer' => 'مبتدأ',
                    ],
                    [
                        'question' => 'ما علامة رفع المبتدأ والخبر غالبًا؟',
                        'choices' => ['الفتحة', 'الياء', 'الضمة', 'السكون'],
                        'answer' => 'الضمة',
                    ],
                ],
            ],
            'english-reading-writing' => [
                'section' => 1,
                'lesson' => 1,
                'title' => 'Reading Checkpoint',
                'passing_score' => 55,
                'time_limit' => 18,
                'max_attempts' => 2,
                'questions' => [
                    [
                        'question' => 'What should you look for first when skimming a text?',
                        'choices' => ['Grammar rules', 'Main idea', 'Page color', 'Dictionary order'],
                        'answer' => 'Main idea',
                    ],
                    [
                        'question' => 'Which sentence is best for a topic sentence?',
                        'choices' => ['It is a nice day.', 'I like apples and oranges.', 'My favorite hobby is reading.', 'The end.'],
                        'answer' => 'My favorite hobby is reading.',
                    ],
                ],
            ],
            'chemistry-basics' => [
                'section' => 1,
                'lesson' => 1,
                'title' => 'اختبار الذرة والعناصر',
                'passing_score' => 58,
                'time_limit' => 20,
                'max_attempts' => 2,
                'questions' => [
                    [
                        'question' => 'What particle has a positive charge?',
                        'choices' => ['Electron', 'Neutron', 'Proton', 'Photon'],
                        'answer' => 'Proton',
                    ],
                    [
                        'question' => 'The atomic number represents the number of:',
                        'choices' => ['Neutrons', 'Protons', 'Molecules', 'Isotopes'],
                        'answer' => 'Protons',
                    ],
                ],
            ],
        ];

        $created = [];

        foreach ($blueprints as $courseSlug => $quiz) {
            $section = $sections[$courseSlug][$quiz['section']];
            $lesson = $lessons[$courseSlug][$quiz['section']][$quiz['lesson']];

            $created[$courseSlug] = Quiz::firstOrCreate(
                [
                    'course_section_id' => $section->id,
                    'title' => $quiz['title'],
                ],
                [
                    'course_id' => $courses[$courseSlug]->id,
                    'lesson_id' => $lesson->id,
                    'description' => 'Short formative quiz for ' . $section->title,
                    'passing_score' => $quiz['passing_score'],
                    'time_limit' => $quiz['time_limit'],
                    'max_attempts' => $quiz['max_attempts'],
                    'is_published' => true,
                    'questions' => $quiz['questions'],
                ]
            );
        }

        return $created;
    }

    /**
     * @param array<string, Quiz> $quizzes
     * @param array<string, User> $students
     */
    private function seedQuizSubmissions(array $quizzes, array $students): void
    {
        $submissions = [
            ['quiz' => 'algebra-foundations', 'student' => 'yasmine', 'attempt' => 1, 'score' => 92, 'total_score' => 100, 'status' => 'passed'],
            ['quiz' => 'physics-motion-waves', 'student' => 'mohamed', 'attempt' => 1, 'score' => 74, 'total_score' => 100, 'status' => 'passed'],
            ['quiz' => 'arabic-grammar-writing', 'student' => 'yasmine', 'attempt' => 1, 'score' => 61, 'total_score' => 100, 'status' => 'passed'],
            ['quiz' => 'english-reading-writing', 'student' => 'noha', 'attempt' => 1, 'score' => 48, 'total_score' => 100, 'status' => 'failed'],
            ['quiz' => 'chemistry-basics', 'student' => 'omar', 'attempt' => 1, 'score' => 67, 'total_score' => 100, 'status' => 'passed'],
        ];

        foreach ($submissions as $submission) {
            QuizSubmission::firstOrCreate(
                [
                    'quiz_id' => $quizzes[$submission['quiz']]->id,
                    'user_id' => $students[$submission['student']]->id,
                    'attempt_number' => $submission['attempt'],
                ],
                [
                    'answers' => [
                        1 => 'A',
                        2 => 'B',
                    ],
                    'score' => $submission['score'],
                    'total_score' => $submission['total_score'],
                    'status' => $submission['status'],
                    'feedback' => $submission['status'] === 'passed'
                        ? 'Good work. Focus on the explanation step for even better results.'
                        : 'Review the lesson recap and retry the quiz.',
                    'submitted_at' => Carbon::parse('2026-08-13 18:00:00'),
                ]
            );
        }
    }

    /**
     * @param array<string, Course> $courses
     *
     * @return array<string, Exam>
     */
    private function seedExams(array $courses): array
    {
        $definitions = [
            'algebra-foundations' => [
                'title' => 'Midterm Exam - Algebra',
                'start' => '2026-08-16 16:00:00',
                'end' => '2026-08-16 18:00:00',
                'duration_minutes' => 90,
                'passing_score' => 60,
                'questions' => [
                    ['question' => 'Solve x + 7 = 15.', 'choices' => ['6', '7', '8', '9'], 'answer' => '8'],
                    ['question' => 'Expand 2(x + 3).', 'choices' => ['2x + 3', '2x + 6', 'x + 6', 'x + 3'], 'answer' => '2x + 6'],
                ],
            ],
            'physics-motion-waves' => [
                'title' => 'Physics Unit Test',
                'start' => '2026-08-17 15:00:00',
                'end' => '2026-08-17 17:00:00',
                'duration_minutes' => 90,
                'passing_score' => 55,
                'questions' => [
                    ['question' => 'Define velocity.', 'choices' => ['Speed with direction', 'Distance only', 'Force only', 'Mass only'], 'answer' => 'Speed with direction'],
                    ['question' => 'Wave amplitude describes:', 'choices' => ['Height from rest position', 'Time taken', 'Speed of light', 'Mass of particle'], 'answer' => 'Height from rest position'],
                ],
            ],
            'arabic-grammar-writing' => [
                'title' => 'امتحان النحو والكتابة',
                'start' => '2026-08-18 14:00:00',
                'end' => '2026-08-18 16:00:00',
                'duration_minutes' => 80,
                'passing_score' => 60,
                'questions' => [
                    ['question' => 'حدد الخبر في جملة: الطقسُ جميلٌ.', 'choices' => ['الطقس', 'جميل', 'في', 'جملة'], 'answer' => 'جميل'],
                    ['question' => 'ما نوع كلمة "قرأ"؟', 'choices' => ['اسم', 'فعل', 'حرف', 'ضمير'], 'answer' => 'فعل'],
                ],
            ],
            'english-reading-writing' => [
                'title' => 'English Skills Final',
                'start' => '2026-08-19 13:00:00',
                'end' => '2026-08-19 15:00:00',
                'duration_minutes' => 85,
                'passing_score' => 58,
                'questions' => [
                    ['question' => 'Choose the correct sentence.', 'choices' => ['She go to school.', 'She goes to school.', 'She going to school.', 'She gone school.'], 'answer' => 'She goes to school.'],
                    ['question' => 'What is a conclusion in a paragraph?', 'choices' => ['First sentence', 'Middle detail', 'Final wrap-up', 'Title only'], 'answer' => 'Final wrap-up'],
                ],
            ],
            'chemistry-basics' => [
                'title' => 'Chemistry Topic Exam',
                'start' => '2026-08-20 12:00:00',
                'end' => '2026-08-20 14:00:00',
                'duration_minutes' => 90,
                'passing_score' => 60,
                'questions' => [
                    ['question' => 'What is the atomic number of an element?', 'choices' => ['Proton count', 'Neutron count', 'Electron shells', 'Molecule count'], 'answer' => 'Proton count'],
                    ['question' => 'Which of these is a chemical reaction indicator?', 'choices' => ['Color change', 'Page number', 'Font size', 'Paper texture'], 'answer' => 'Color change'],
                ],
            ],
        ];

        $created = [];

        foreach ($definitions as $courseSlug => $exam) {
            $created[$courseSlug] = Exam::firstOrCreate(
                [
                    'course_id' => $courses[$courseSlug]->id,
                    'title' => $exam['title'],
                ],
                [
                    'description' => 'End-of-unit exam for ' . $courses[$courseSlug]->title,
                    'start_date' => Carbon::parse($exam['start']),
                    'end_date' => Carbon::parse($exam['end']),
                    'duration_minutes' => $exam['duration_minutes'],
                    'passing_score' => $exam['passing_score'],
                    'questions' => $exam['questions'],
                ]
            );
        }

        return $created;
    }

    /**
     * @param array<string, Exam> $exams
     * @param array<string, User> $students
     */
    private function seedExamAttempts(array $exams, array $students): void
    {
        $attempts = [
            ['exam' => 'algebra-foundations', 'student' => 'yasmine', 'score' => 88, 'status' => 'passed', 'started' => '2026-08-16 16:10:00', 'submitted' => '2026-08-16 17:05:00'],
            ['exam' => 'physics-motion-waves', 'student' => 'mohamed', 'score' => 72, 'status' => 'passed', 'started' => '2026-08-17 15:10:00', 'submitted' => '2026-08-17 16:15:00'],
            ['exam' => 'arabic-grammar-writing', 'student' => 'yasmine', 'score' => 64, 'status' => 'passed', 'started' => '2026-08-18 14:05:00', 'submitted' => '2026-08-18 15:00:00'],
            ['exam' => 'english-reading-writing', 'student' => 'noha', 'score' => 49, 'status' => 'failed', 'started' => '2026-08-19 13:05:00', 'submitted' => '2026-08-19 14:20:00'],
            ['exam' => 'chemistry-basics', 'student' => 'omar', 'score' => 0, 'status' => 'ongoing', 'started' => '2026-08-20 12:30:00', 'submitted' => null],
        ];

        foreach ($attempts as $attempt) {
            ExamAttempt::firstOrCreate(
                [
                    'exam_id' => $exams[$attempt['exam']]->id,
                    'user_id' => $students[$attempt['student']]->id,
                    'started_at' => Carbon::parse($attempt['started']),
                ],
                [
                    'answers' => [
                        1 => 'A',
                        2 => 'C',
                    ],
                    'score' => $attempt['score'],
                    'status' => $attempt['status'],
                    'submitted_at' => $attempt['submitted'] ? Carbon::parse($attempt['submitted']) : null,
                ]
            );
        }
    }

    /**
     * @param array<string, CourseSection> $sections
     *
     * @return array<string, Assignment>
     */
    private function seedAssignments(array $sections): array
    {
        $definitions = [
            'algebra-foundations' => [
                'section' => 2,
                'title' => 'واجب المعادلات الخطية',
                'description' => 'Solve four linear equations and show your steps clearly.',
                'due_date' => '2026-08-21 23:59:00',
            ],
            'physics-motion-waves' => [
                'section' => 2,
                'title' => 'Physics worksheet',
                'description' => 'Answer the motion and waves practice sheet.',
                'due_date' => '2026-08-22 23:59:00',
            ],
            'arabic-grammar-writing' => [
                'section' => 2,
                'title' => 'واجب التعبير',
                'description' => 'Write a complete paragraph on study habits.',
                'due_date' => '2026-08-21 23:59:00',
            ],
            'english-reading-writing' => [
                'section' => 2,
                'title' => 'Writing assignment',
                'description' => 'Write a short formal email and a paragraph.',
                'due_date' => '2026-08-22 23:59:00',
            ],
            'chemistry-basics' => [
                'section' => 2,
                'title' => 'Chemistry lab questions',
                'description' => 'Balance the equations and explain the reaction types.',
                'due_date' => '2026-08-23 23:59:00',
            ],
        ];

        $created = [];

        foreach ($definitions as $courseSlug => $assignment) {
            $section = $sections[$courseSlug][$assignment['section']];

            $created[$courseSlug] = Assignment::firstOrCreate(
                [
                    'course_section_id' => $section->id,
                    'title' => $assignment['title'],
                ],
                [
                    'description' => $assignment['description'],
                    'due_date' => Carbon::parse($assignment['due_date']),
                    'max_score' => 100,
                    'file_path' => 'assignments/' . Str::slug($assignment['title']) . '.pdf',
                ]
            );
        }

        return $created;
    }

    /**
     * @param array<string, Assignment> $assignments
     * @param array<string, User> $students
     * @param array<string, User> $teachers
     */
    private function seedAssignmentSubmissions(array $assignments, array $students, array $teachers): void
    {
        $rows = [
            ['assignment' => 'algebra-foundations', 'student' => 'yasmine', 'score' => 94, 'graded_by' => 'salma'],
            ['assignment' => 'physics-motion-waves', 'student' => 'mohamed', 'score' => 81, 'graded_by' => 'ahmed'],
            ['assignment' => 'arabic-grammar-writing', 'student' => 'yasmine', 'score' => 88, 'graded_by' => 'salma'],
            ['assignment' => 'english-reading-writing', 'student' => 'noha', 'score' => 73, 'graded_by' => 'ahmed'],
            ['assignment' => 'chemistry-basics', 'student' => 'omar', 'score' => 86, 'graded_by' => 'salma'],
        ];

        foreach ($rows as $row) {
            AssignmentSubmission::firstOrCreate(
                [
                    'assignment_id' => $assignments[$row['assignment']]->id,
                    'student_id' => $students[$row['student']]->id,
                ],
                [
                    'file_path' => 'submissions/' . $row['assignment'] . '-' . $row['student'] . '.pdf',
                    'text_response' => 'Submitted a structured answer with clear steps and examples.',
                    'score' => $row['score'],
                    'feedback' => 'Solid submission. Review the explanation and keep practicing.',
                    'graded_at' => Carbon::parse('2026-08-13 17:00:00'),
                    'graded_by' => $teachers[$row['graded_by']]->id,
                ]
            );
        }
    }

    /**
     * @param array<string, Course> $courses
     * @param array<string, User> $teachers
     *
     * @return array<string, Group>
     */
    private function seedGroups(array $courses, array $teachers): array
    {
        $definitions = [
            'algebra-foundations' => [
                'name' => 'Group A - Algebra 3',
                'description' => 'Prep 3 algebra support group with weekly review sessions.',
                'teacher' => 'salma',
            ],
            'physics-motion-waves' => [
                'name' => 'Group P - Physics 2',
                'description' => 'Interactive physics group focused on problem solving.',
                'teacher' => 'ahmed',
            ],
            'arabic-grammar-writing' => [
                'name' => 'Group AR - Arabic Writing',
                'description' => 'Arabic grammar practice and writing drills.',
                'teacher' => 'salma',
            ],
            'english-reading-writing' => [
                'name' => 'Group EN - English Skills',
                'description' => 'Reading and writing clinic for school exam prep.',
                'teacher' => 'ahmed',
            ],
            'chemistry-basics' => [
                'name' => 'Group CH - Chemistry Basics',
                'description' => 'Foundation chemistry group with revision support.',
                'teacher' => 'salma',
            ],
        ];

        $created = [];

        foreach ($definitions as $courseSlug => $group) {
            $created[$courseSlug] = Group::firstOrCreate(
                ['name' => $group['name']],
                [
                    'description' => $group['description'],
                    'teacher_id' => $teachers[$group['teacher']]->id,
                    'course_id' => $courses[$courseSlug]->id,
                ]
            );
        }

        return $created;
    }

    /**
     * @param array<string, Group> $groups
     * @param array<string, User> $students
     */
    private function seedGroupMemberships(array $groups, array $students): void
    {
        $memberships = [
            ['group' => 'algebra-foundations', 'student' => 'yasmine'],
            ['group' => 'algebra-foundations', 'student' => 'mohamed'],
            ['group' => 'physics-motion-waves', 'student' => 'mohamed'],
            ['group' => 'physics-motion-waves', 'student' => 'noha'],
            ['group' => 'arabic-grammar-writing', 'student' => 'yasmine'],
            ['group' => 'arabic-grammar-writing', 'student' => 'noha'],
            ['group' => 'english-reading-writing', 'student' => 'noha'],
            ['group' => 'english-reading-writing', 'student' => 'omar'],
            ['group' => 'chemistry-basics', 'student' => 'omar'],
            ['group' => 'chemistry-basics', 'student' => 'noha'],
        ];

        foreach ($memberships as $membership) {
            DB::table('group_user')->updateOrInsert(
                [
                    'group_id' => $groups[$membership['group']]->id,
                    'user_id' => $students[$membership['student']]->id,
                ],
                [
                    'created_at' => Carbon::parse('2026-08-10 10:00:00'),
                    'updated_at' => Carbon::parse('2026-08-10 10:00:00'),
                ]
            );
        }
    }

    /**
     * @param array<string, Group> $groups
     */
    private function seedGroupSchedules(array $groups): void
    {
        $schedules = [
            'algebra-foundations' => [
                ['day' => 'monday', 'start_time' => '17:00:00', 'end_time' => '18:30:00'],
                ['day' => 'thursday', 'start_time' => '17:00:00', 'end_time' => '18:30:00'],
            ],
            'physics-motion-waves' => [
                ['day' => 'tuesday', 'start_time' => '18:00:00', 'end_time' => '19:30:00'],
                ['day' => 'friday', 'start_time' => '18:00:00', 'end_time' => '19:30:00'],
            ],
            'arabic-grammar-writing' => [
                ['day' => 'sunday', 'start_time' => '16:00:00', 'end_time' => '17:30:00'],
                ['day' => 'wednesday', 'start_time' => '16:00:00', 'end_time' => '17:30:00'],
            ],
            'english-reading-writing' => [
                ['day' => 'monday', 'start_time' => '19:00:00', 'end_time' => '20:15:00'],
                ['day' => 'thursday', 'start_time' => '19:00:00', 'end_time' => '20:15:00'],
            ],
            'chemistry-basics' => [
                ['day' => 'tuesday', 'start_time' => '16:00:00', 'end_time' => '17:15:00'],
                ['day' => 'saturday', 'start_time' => '16:00:00', 'end_time' => '17:15:00'],
            ],
        ];

        foreach ($schedules as $groupSlug => $rows) {
            foreach ($rows as $row) {
                GroupSession::firstOrCreate(
                    [
                        'group_id' => $groups[$groupSlug]->id,
                        'day' => $row['day'],
                        'start_time' => $row['start_time'],
                        'end_time' => $row['end_time'],
                    ],
                    []
                );
            }
        }
    }

    /**
     * @param array<string, User> $students
     * @param array<string, Course> $courses
     */
    private function seedEnrollments(array $students, array $courses): void
    {
        $rows = [
            ['student' => 'yasmine', 'course' => 'algebra-foundations', 'status' => 'active', 'progress' => 42],
            ['student' => 'yasmine', 'course' => 'arabic-grammar-writing', 'status' => 'active', 'progress' => 35],
            ['student' => 'yasmine', 'course' => 'english-reading-writing', 'status' => 'completed', 'progress' => 100],
            ['student' => 'mohamed', 'course' => 'algebra-foundations', 'status' => 'active', 'progress' => 28],
            ['student' => 'mohamed', 'course' => 'physics-motion-waves', 'status' => 'active', 'progress' => 55],
            ['student' => 'noha', 'course' => 'physics-motion-waves', 'status' => 'active', 'progress' => 48],
            ['student' => 'noha', 'course' => 'english-reading-writing', 'status' => 'active', 'progress' => 60],
            ['student' => 'noha', 'course' => 'chemistry-basics', 'status' => 'expired', 'progress' => 100],
            ['student' => 'omar', 'course' => 'physics-motion-waves', 'status' => 'completed', 'progress' => 100],
            ['student' => 'omar', 'course' => 'chemistry-basics', 'status' => 'active', 'progress' => 40],
        ];

        foreach ($rows as $row) {
            Enrollment::firstOrCreate(
                [
                    'student_id' => $students[$row['student']]->id,
                    'course_id' => $courses[$row['course']]->id,
                ],
                [
                    'status' => $row['status'],
                    'progress_percent' => $row['progress'],
                ]
            );
        }
    }

    /**
     * @param array<string, Group> $groups
     * @param array<string, User> $students
     */
    private function seedAttendances(array $groups, array $students): void
    {
        $dates = [
            '2026-08-11',
            '2026-08-13',
            '2026-08-14',
        ];

        $rows = [
            ['group' => 'algebra-foundations', 'student' => 'yasmine', 'status' => 'present'],
            ['group' => 'algebra-foundations', 'student' => 'mohamed', 'status' => 'late'],
            ['group' => 'physics-motion-waves', 'student' => 'mohamed', 'status' => 'present'],
            ['group' => 'physics-motion-waves', 'student' => 'noha', 'status' => 'absent'],
            ['group' => 'arabic-grammar-writing', 'student' => 'yasmine', 'status' => 'present'],
            ['group' => 'arabic-grammar-writing', 'student' => 'noha', 'status' => 'excused'],
            ['group' => 'english-reading-writing', 'student' => 'noha', 'status' => 'present'],
            ['group' => 'english-reading-writing', 'student' => 'omar', 'status' => 'present'],
            ['group' => 'chemistry-basics', 'student' => 'omar', 'status' => 'late'],
            ['group' => 'chemistry-basics', 'student' => 'noha', 'status' => 'present'],
        ];

        foreach ($rows as $index => $row) {
            Attendance::firstOrCreate(
                [
                    'group_id' => $groups[$row['group']]->id,
                    'student_id' => $students[$row['student']]->id,
                    'date' => Carbon::parse($dates[$index % count($dates)]),
                ],
                [
                    'status' => $row['status'],
                ]
            );
        }
    }

    /**
     * @param array<string, User> $parents
     * @param array<string, User> $students
     */
    private function seedParentLinks(array $parents, array $students): void
    {
        $rows = [
            ['parent' => 'huda', 'student' => 'yasmine'],
            ['parent' => 'huda', 'student' => 'mohamed'],
            ['parent' => 'karim', 'student' => 'noha'],
            ['parent' => 'karim', 'student' => 'omar'],
        ];

        foreach ($rows as $row) {
            DB::table('parent_student')->updateOrInsert(
                [
                    'parent_id' => $parents[$row['parent']]->id,
                    'student_id' => $students[$row['student']]->id,
                ],
                [
                    'created_at' => Carbon::parse('2026-08-09 12:00:00'),
                    'updated_at' => Carbon::parse('2026-08-09 12:00:00'),
                ]
            );
        }
    }

    /**
     * @param array<string, User> $parents
     * @param array<string, User> $teachers
     */
    private function seedCommunicationLogs(array $parents, array $teachers): void
    {
        $rows = [
            [
                'parent' => 'huda',
                'teacher' => 'salma',
                'message' => 'Discussed Yasmine\'s algebra progress and the next homework plan.',
                'type' => 'call',
                'logged_at' => '2026-08-12 19:10:00',
            ],
            [
                'parent' => 'huda',
                'teacher' => 'salma',
                'message' => 'Sent a follow-up email with extra practice sheets.',
                'type' => 'email',
                'logged_at' => '2026-08-13 08:45:00',
            ],
            [
                'parent' => 'karim',
                'teacher' => 'ahmed',
                'message' => 'Quick in-person check-in after the physics session.',
                'type' => 'in_person',
                'logged_at' => '2026-08-13 18:40:00',
            ],
            [
                'parent' => 'karim',
                'teacher' => 'ahmed',
                'message' => 'Shared an SMS reminder for the upcoming English assignment.',
                'type' => 'sms',
                'logged_at' => '2026-08-14 09:15:00',
            ],
        ];

        foreach ($rows as $row) {
            CommunicationLog::firstOrCreate(
                [
                    'parent_id' => $parents[$row['parent']]->id,
                    'teacher_id' => $teachers[$row['teacher']]->id,
                    'logged_at' => Carbon::parse($row['logged_at']),
                ],
                [
                    'message' => $row['message'],
                    'type' => $row['type'],
                ]
            );
        }
    }

    /**
     * @param array<string, User> $students
     * @param array<string, User> $teachers
     */
    private function seedStudentNotes(array $students, array $teachers): void
    {
        $rows = [
            ['student' => 'yasmine', 'teacher' => 'salma', 'note' => 'Strong participation. Needs faster equation solving under time pressure.'],
            ['student' => 'mohamed', 'teacher' => 'ahmed', 'note' => 'Improving steadily in physics. Should revise graph interpretation.'],
            ['student' => 'noha', 'teacher' => 'ahmed', 'note' => 'Good writing structure, but vocabulary range needs work.'],
            ['student' => 'omar', 'teacher' => 'salma', 'note' => 'Excellent chemistry basics. Encourage more practice on balancing equations.'],
        ];

        foreach ($rows as $row) {
            StudentNote::firstOrCreate(
                [
                    'student_id' => $students[$row['student']]->id,
                    'teacher_id' => $teachers[$row['teacher']]->id,
                    'note' => $row['note'],
                ]
            );
        }
    }

    /**
     * @param array<string, User> $students
     * @param array<string, Course> $courses
     * @param User $admin
     */
    private function seedPayments(array $students, array $courses, User $admin): void
    {
        $rows = [
            ['student' => 'yasmine', 'course' => 'algebra-foundations', 'amount' => 425, 'method' => 'instapay', 'status' => 'approved', 'sender_phone' => '01011110001', 'approved_at' => '2026-08-10 14:20:00'],
            ['student' => 'mohamed', 'course' => 'physics-motion-waves', 'amount' => 460, 'method' => 'vodafone cash', 'status' => 'approved', 'sender_phone' => '01011110002', 'approved_at' => '2026-08-11 16:10:00'],
            ['student' => 'noha', 'course' => 'english-reading-writing', 'amount' => 405, 'method' => 'cash', 'status' => 'pending', 'sender_phone' => '01011110003', 'approved_at' => null],
            ['student' => 'omar', 'course' => 'chemistry-basics', 'amount' => 475, 'method' => 'instapay', 'status' => 'approved', 'sender_phone' => '01011110004', 'approved_at' => '2026-08-12 12:30:00'],
            ['student' => 'yasmine', 'course' => 'arabic-grammar-writing', 'amount' => 390, 'method' => 'bank transfer', 'status' => 'rejected', 'sender_phone' => '01011110005', 'approved_at' => null],
            ['student' => 'noha', 'course' => 'chemistry-basics', 'amount' => 475, 'method' => 'instapay', 'status' => 'approved', 'sender_phone' => '01011110006', 'approved_at' => '2026-08-14 11:05:00'],
        ];

        foreach ($rows as $row) {
            Payment::firstOrCreate(
                [
                    'user_id' => $students[$row['student']]->id,
                    'course_id' => $courses[$row['course']]->id,
                    'amount' => $row['amount'],
                    'payment_method' => $row['method'],
                    'status' => $row['status'],
                ],
                [
                    'sender_phone' => $row['sender_phone'],
                    'receipt_path' => 'receipts/' . $row['student'] . '-' . $row['course'] . '.jpg',
                    'approved_at' => $row['approved_at'] ? Carbon::parse($row['approved_at']) : null,
                    'approved_by' => $row['status'] === 'approved' ? $admin->id : null,
                ]
            );
        }
    }

    /**
     * @param array<string, User> $students
     * @param array<string, Course> $courses
     */
    private function seedInstallments(array $students, array $courses): void
    {
        $rows = [
            ['student' => 'yasmine', 'course' => 'algebra-foundations', 'amount' => 425, 'due_date' => '2026-08-20', 'status' => 'pending', 'paid_at' => null],
            ['student' => 'yasmine', 'course' => 'arabic-grammar-writing', 'amount' => 390, 'due_date' => '2026-08-10', 'status' => 'paid', 'paid_at' => '2026-08-09 15:40:00'],
            ['student' => 'mohamed', 'course' => 'physics-motion-waves', 'amount' => 460, 'due_date' => '2026-08-18', 'status' => 'paid', 'paid_at' => '2026-08-11 16:10:00'],
            ['student' => 'noha', 'course' => 'english-reading-writing', 'amount' => 405, 'due_date' => '2026-08-19', 'status' => 'pending', 'paid_at' => null],
            ['student' => 'noha', 'course' => 'chemistry-basics', 'amount' => 475, 'due_date' => '2026-08-12', 'status' => 'overdue', 'paid_at' => null],
            ['student' => 'omar', 'course' => 'chemistry-basics', 'amount' => 475, 'due_date' => '2026-08-23', 'status' => 'pending', 'paid_at' => null],
        ];

        foreach ($rows as $row) {
            Installment::firstOrCreate(
                [
                    'student_id' => $students[$row['student']]->id,
                    'course_id' => $courses[$row['course']]->id,
                    'due_date' => Carbon::parse($row['due_date']),
                ],
                [
                    'amount' => $row['amount'],
                    'status' => $row['status'],
                    'paid_at' => $row['paid_at'] ? Carbon::parse($row['paid_at']) : null,
                ]
            );
        }
    }

    /**
     * @param array<string, User> $students
     * @param array<string, Course> $courses
     */
    private function seedCertificates(array $students, array $courses): void
    {
        $rows = [
            ['student' => 'yasmine', 'course' => 'algebra-foundations', 'code' => 'CERT-ALG-2026-0001', 'issued_at' => '2026-08-14 18:00:00'],
            ['student' => 'mohamed', 'course' => 'physics-motion-waves', 'code' => 'CERT-PHY-2026-0002', 'issued_at' => '2026-08-14 18:00:00'],
            ['student' => 'omar', 'course' => 'chemistry-basics', 'code' => 'CERT-CHE-2026-0003', 'issued_at' => '2026-08-14 18:00:00'],
        ];

        foreach ($rows as $row) {
            Certificate::firstOrCreate(
                [
                    'certificate_code' => $row['code'],
                ],
                [
                    'course_id' => $courses[$row['course']]->id,
                    'student_id' => $students[$row['student']]->id,
                    'issued_at' => Carbon::parse($row['issued_at']),
                ]
            );
        }
    }

    /**
     * @param array{admin: User, teachers: array<string, User>, students: array<string, User>, parents: array<string, User>} $users
     */
    private function seedLoginActivities(array $users): void
    {
        $rows = [
            ['user' => $users['admin'], 'event' => 'login', 'ip' => '102.44.12.10', 'agent' => 'Chrome on Windows', 'at' => '2026-08-14 08:00:00'],
            ['user' => $users['teachers']['salma'], 'event' => 'login', 'ip' => '102.44.12.11', 'agent' => 'Chrome on Windows', 'at' => '2026-08-14 08:10:00'],
            ['user' => $users['teachers']['ahmed'], 'event' => 'login', 'ip' => '102.44.12.12', 'agent' => 'Safari on macOS', 'at' => '2026-08-14 08:15:00'],
            ['user' => $users['students']['yasmine'], 'event' => 'login', 'ip' => '197.34.10.21', 'agent' => 'Mobile Safari', 'at' => '2026-08-14 17:30:00'],
            ['user' => $users['students']['noha'], 'event' => 'failed_login', 'ip' => '197.34.10.31', 'agent' => 'Chrome on Android', 'at' => '2026-08-14 17:35:00'],
            ['user' => $users['parents']['huda'], 'event' => 'password_reset', 'ip' => '197.34.10.41', 'agent' => 'Chrome on Windows', 'at' => '2026-08-13 21:05:00'],
        ];

        foreach ($rows as $row) {
            LoginActivity::firstOrCreate(
                [
                    'user_id' => $row['user']->id,
                    'event' => $row['event'],
                    'occurred_at' => Carbon::parse($row['at']),
                ],
                [
                    'ip_address' => $row['ip'],
                    'user_agent' => $row['agent'],
                ]
            );
        }
    }

    /**
     * @param array{admin: User, teachers: array<string, User>, students: array<string, User>, parents: array<string, User>} $users
     * @param array<string, Course> $courses
     * @param array<string, Group> $groups
     */
    private function seedActivityLogs(array $users, array $courses, array $groups): void
    {
        $rows = [
            [
                'causer' => $users['admin'],
                'event' => 'created',
                'subject_type' => Course::class,
                'subject_id' => $courses['algebra-foundations']->id,
                'description' => 'Created algebra course and published it for students.',
                'properties' => ['title' => $courses['algebra-foundations']->title],
                'created_at' => '2026-08-10 09:15:00',
            ],
            [
                'causer' => $users['teachers']['salma'],
                'event' => 'updated',
                'subject_type' => Group::class,
                'subject_id' => $groups['algebra-foundations']->id,
                'description' => 'Adjusted the algebra group schedule for the new week.',
                'properties' => ['day' => 'thursday'],
                'created_at' => '2026-08-12 20:05:00',
            ],
            [
                'causer' => $users['students']['yasmine'],
                'event' => 'submitted',
                'subject_type' => Quiz::class,
                'subject_id' => Quiz::where('title', 'اختبار الوحدة الأولى - الجبر')->value('id'),
                'description' => 'Submitted the algebra quiz attempt.',
                'properties' => ['score' => 92],
                'created_at' => '2026-08-13 18:00:00',
            ],
            [
                'causer' => $users['students']['mohamed'],
                'event' => 'submitted',
                'subject_type' => Assignment::class,
                'subject_id' => Assignment::where('title', 'Physics worksheet')->value('id'),
                'description' => 'Uploaded the physics worksheet submission.',
                'properties' => ['file_path' => 'submissions/physics-motion-waves-mohamed.pdf'],
                'created_at' => '2026-08-13 19:15:00',
            ],
            [
                'causer' => $users['admin'],
                'event' => 'approved',
                'subject_type' => Payment::class,
                'subject_id' => Payment::where('status', 'approved')->value('id'),
                'description' => 'Approved the latest payment record.',
                'properties' => ['status' => 'approved'],
                'created_at' => '2026-08-14 12:30:00',
            ],
            [
                'causer' => $users['teachers']['ahmed'],
                'event' => 'issued',
                'subject_type' => Certificate::class,
                'subject_id' => Certificate::where('certificate_code', 'CERT-PHY-2026-0002')->value('id'),
                'description' => 'Issued a completion certificate for physics.',
                'properties' => ['certificate_code' => 'CERT-PHY-2026-0002'],
                'created_at' => '2026-08-14 18:10:00',
            ],
        ];

        foreach ($rows as $row) {
            ActivityLog::firstOrCreate(
                [
                    'causer_id' => $row['causer']->id,
                    'event' => $row['event'],
                    'subject_type' => $row['subject_type'],
                    'subject_id' => $row['subject_id'],
                    'created_at' => Carbon::parse($row['created_at']),
                ],
                [
                    'description' => $row['description'],
                    'properties' => $row['properties'],
                    'ip_address' => '102.44.12.10',
                    'user_agent' => 'Seeder',
                ]
            );
        }
    }

    /**
     * @param array{admin: User, teachers: array<string, User>, students: array<string, User>, parents: array<string, User>} $users
     * @param array<string, Course> $courses
     */
    private function seedNotifications(array $users, array $courses): void
    {
        $notifications = [
            [
                'id' => 'c6c5f7d8-5a7b-4af0-9e3d-100000000001',
                'user' => $users['students']['yasmine'],
                'type' => 'App\\Notifications\\EnrollmentConfirmed',
                'data' => [
                    'title' => 'Enrollment confirmed',
                    'body' => 'You are now enrolled in ' . $courses['algebra-foundations']->title,
                    'course_id' => $courses['algebra-foundations']->id,
                ],
                'read_at' => null,
            ],
            [
                'id' => 'c6c5f7d8-5a7b-4af0-9e3d-100000000002',
                'user' => $users['students']['noha'],
                'type' => 'App\\Notifications\\AssignmentGraded',
                'data' => [
                    'title' => 'Assignment graded',
                    'body' => 'Your English writing assignment has been graded.',
                    'score' => 73,
                ],
                'read_at' => Carbon::parse('2026-08-14 09:00:00'),
            ],
            [
                'id' => 'c6c5f7d8-5a7b-4af0-9e3d-100000000003',
                'user' => $users['parents']['huda'],
                'type' => 'App\\Notifications\\ProgressUpdate',
                'data' => [
                    'title' => 'Weekly progress update',
                    'body' => 'Yasmine completed the algebra quiz with excellent results.',
                ],
                'read_at' => null,
            ],
            [
                'id' => 'c6c5f7d8-5a7b-4af0-9e3d-100000000004',
                'user' => $users['admin'],
                'type' => 'App\\Notifications\\PaymentApproved',
                'data' => [
                    'title' => 'Payment approved',
                    'body' => 'A student payment was approved successfully.',
                    'amount' => 460,
                ],
                'read_at' => Carbon::parse('2026-08-14 12:45:00'),
            ],
        ];

        foreach ($notifications as $notification) {
            DB::table('notifications')->updateOrInsert(
                ['id' => $notification['id']],
                [
                    'type' => $notification['type'],
                    'notifiable_type' => User::class,
                    'notifiable_id' => $notification['user']->id,
                    'data' => json_encode($notification['data'], JSON_UNESCAPED_UNICODE),
                    'read_at' => $notification['read_at'],
                    'created_at' => Carbon::parse('2026-08-14 08:00:00'),
                    'updated_at' => Carbon::parse('2026-08-14 08:00:00'),
                ]
            );
        }
    }

    private function lessonDescription(string $title): string
    {
        return match ($title) {
            'المتغيرات والتعابير' => 'Introduce variables, constants, and how to build algebraic expressions.',
            'تبسيط الحدود المتشابهة' => 'Practice combining like terms with worked examples.',
            'حل المعادلات من خطوة واحدة' => 'Solve simple linear equations using inverse operations.',
            'مسائل تطبيقية على المعادلات' => 'Translate word problems into equations and solve them.',
            'السرعة والسرعة المتجهة' => 'Differentiate speed, velocity, and their units.',
            'القوانين الأساسية للحركة' => 'Apply Newton-style reasoning to everyday motion problems.',
            'خصائص الموجة' => 'Understand wavelength, amplitude, and frequency.',
            'تطبيقات على الصوت' => 'Explore how sound travels and how it is measured.',
            'المبتدأ والخبر' => 'Build strong sentence analysis skills for Arabic grammar.',
            'الفاعل والمفعول به' => 'Identify core sentence roles in Arabic examples.',
            'كتابة الفقرة' => 'Plan and write a clear paragraph with logical flow.',
            'أخطاء شائعة في الكتابة' => 'Spot common spelling and punctuation mistakes.',
            'Reading a short passage' => 'Practice skimming and scanning a reading passage.',
            'Vocabulary in context' => 'Infer meaning from context clues.',
            'Writing a paragraph' => 'Organize supporting ideas in a short paragraph.',
            'Formal and informal email' => 'Use suitable tone and structure in emails.',
            'مكونات الذرة' => 'Learn the parts of the atom and basic atomic numbers.',
            'الجدول الدوري' => 'Read the periodic table and recognize key trends.',
            'أنواع التفاعلات' => 'Classify common chemistry reaction types.',
            'موازنة المعادلات' => 'Balance chemical equations step by step.',
            default => 'Practical lesson content for the student dashboard.',
        };
    }
}
