<?php

namespace Database\Seeders\University;

/**
 * Reference data for the full university seed: the academic structure,
 * per-department course topics, Nigerian names and places, employers.
 * Plain arrays, no Faker, so the seeder also runs where dev packages are
 * not installed.
 */
final class Catalogue
{
    public const FOUNDED = 2014;

    /** code => [name, [department codes]] */
    public const FACULTIES = [
        'SICT' => ['School of Information and Communication Technology', ['CSC', 'IFT', 'CYB']],
        'SEET' => ['School of Engineering and Engineering Technology', ['EEE', 'MEE', 'CVE']],
        'SOPS' => ['School of Physical Sciences', ['MTH', 'PHY', 'ICH']],
        'SMAT' => ['School of Management Technology', ['ACC', 'PMT']],
        'SAAT' => ['School of Agriculture and Agricultural Technology', ['AEC']],
        'DGS' => ['Directorate of General Studies', ['GST']],
    ];

    /**
     * code => [department name, programme name, award, years, base intake, first intake year, fee group]
     * The fee group decides the laboratory/workshop levy.
     */
    public const DEPARTMENTS = [
        'CSC' => ['Computer Science', 'B.Sc Computer Science', 'BSC', 4, 14, 2014, 'science'],
        'IFT' => ['Information Technology', 'B.Tech Information Technology', 'BTECH', 4, 9, 2014, 'science'],
        'CYB' => ['Cyber Security', 'B.Tech Cyber Security', 'BTECH', 4, 8, 2019, 'science'],
        'EEE' => ['Electrical and Electronic Engineering', 'B.Eng Electrical and Electronic Engineering', 'BENG', 5, 10, 2014, 'engineering'],
        'MEE' => ['Mechanical Engineering', 'B.Eng Mechanical Engineering', 'BENG', 5, 9, 2014, 'engineering'],
        'CVE' => ['Civil Engineering', 'B.Eng Civil Engineering', 'BENG', 5, 9, 2015, 'engineering'],
        'MTH' => ['Mathematics', 'B.Tech Mathematics', 'BTECH', 4, 6, 2014, 'science'],
        'PHY' => ['Physics', 'B.Tech Physics', 'BTECH', 4, 5, 2014, 'science'],
        'ICH' => ['Industrial Chemistry', 'B.Tech Industrial Chemistry', 'BTECH', 4, 7, 2014, 'science'],
        'ACC' => ['Accounting', 'B.Sc Accounting', 'BSC', 4, 13, 2014, 'management'],
        'PMT' => ['Project Management Technology', 'B.Tech Project Management Technology', 'BTECH', 4, 7, 2016, 'management'],
        'AEC' => ['Agricultural Economics', 'B.Agric Agricultural Economics', 'BAGRIC', 4, 6, 2015, 'science'],
        'GST' => ['General Studies', null, null, 0, 0, 2014, null],
    ];

    /** Programmes whose students go on SIWES, and the level they go at. */
    public const SIWES_LEVEL = ['CSC' => 3, 'IFT' => 3, 'CYB' => 3, 'EEE' => 4, 'MEE' => 4, 'CVE' => 4, 'PHY' => 3, 'ICH' => 3, 'AEC' => 3];

    /**
     * Topics per department, lowest level first: five each for 100 and 200
     * level, six for every level above. Each topic gives a first-semester
     * course ("… I", odd code) and a second-semester one ("… II", even code).
     */
    public const TOPICS = [
        'CSC' => [
            'Introduction to Computing', 'Computer Programming', 'Discrete Structures', 'Calculus for Computing', 'Digital Logic Fundamentals',
            'Object-Oriented Programming', 'Data Structures', 'Computer Architecture', 'Linear Algebra for Computing', 'Systems Analysis and Design',
            'Operating Systems', 'Database Systems', 'Algorithms and Complexity', 'Computer Networks', 'Software Engineering', 'Numerical Methods',
            'Artificial Intelligence', 'Compiler Construction', 'Distributed Systems', 'Computer Graphics', 'Human-Computer Interaction', 'Final Year Project',
        ],
        'IFT' => [
            'Introduction to Information Technology', 'Programming Concepts', 'Mathematics for IT', 'Web Fundamentals', 'Digital Literacy and Communication',
            'Web Application Development', 'Data Management', 'Computer Hardware and Maintenance', 'Network Fundamentals', 'Statistics for IT',
            'Systems Administration', 'Enterprise Database Applications', 'Mobile Application Development', 'IT Project Management', 'Cloud Computing', 'Information Systems Audit',
            'E-Commerce Technologies', 'Business Intelligence', 'IT Service Management', 'Information Systems Security', 'Emerging Technologies', 'Final Year Project',
        ],
        'CYB' => [
            'Introduction to Cyber Security', 'Programming for Security', 'Discrete Mathematics', 'Computer Systems Fundamentals', 'Ethics and Cyber Law',
            'Cryptography', 'Network Security Fundamentals', 'Operating Systems Security', 'Secure Coding', 'Probability for Security',
            'Ethical Hacking', 'Digital Forensics', 'Security Risk Management', 'Malware Analysis', 'Intrusion Detection Systems', 'Security Architecture',
            'Incident Response', 'Cloud Security', 'Blockchain and Security', 'Security Policy and Governance', 'Penetration Testing', 'Final Year Project',
        ],
        'EEE' => [
            'Engineering Mathematics', 'Engineering Drawing', 'Workshop Practice', 'Applied Electricity', 'Introduction to Engineering',
            'Circuit Theory', 'Electronics', 'Engineering Mechanics', 'Electrical Measurements', 'Computer Programming for Engineers',
            'Electromagnetic Fields', 'Signals and Systems', 'Electrical Machines', 'Analogue Electronics', 'Digital Electronics', 'Engineering Statistics',
            'Power Systems', 'Control Engineering', 'Communication Principles', 'Microprocessor Systems', 'Power Electronics', 'Instrumentation',
            'High Voltage Engineering', 'Digital Signal Processing', 'Telecommunication Systems', 'Renewable Energy Systems', 'Engineering Management', 'Final Year Project',
        ],
        'MEE' => [
            'Engineering Mathematics', 'Engineering Drawing', 'Workshop Practice', 'Engineering Physics', 'Introduction to Engineering',
            'Strength of Materials', 'Thermodynamics', 'Engineering Mechanics', 'Materials Science', 'Computer Programming for Engineers',
            'Fluid Mechanics', 'Machine Design', 'Manufacturing Processes', 'Heat Transfer', 'Dynamics of Machines', 'Engineering Statistics',
            'Internal Combustion Engines', 'Refrigeration and Air Conditioning', 'Mechatronics', 'Vibration Analysis', 'Production Engineering', 'Engineering Economics',
            'Turbomachinery', 'Automotive Engineering', 'Computer-Aided Design', 'Maintenance Engineering', 'Engineering Management', 'Final Year Project',
        ],
        'CVE' => [
            'Engineering Mathematics', 'Engineering Drawing', 'Workshop Practice', 'Engineering Physics', 'Introduction to Engineering',
            'Strength of Materials', 'Engineering Surveying', 'Engineering Mechanics', 'Building Materials', 'Computer Programming for Engineers',
            'Structural Analysis', 'Soil Mechanics', 'Hydraulics', 'Reinforced Concrete Design', 'Highway Engineering', 'Engineering Statistics',
            'Foundation Engineering', 'Steel Design', 'Water Resources Engineering', 'Transportation Engineering', 'Construction Management', 'Environmental Engineering',
            'Prestressed Concrete', 'Geotechnical Engineering', 'Coastal and Harbour Engineering', 'Quantity Surveying', 'Engineering Management', 'Final Year Project',
        ],
        'MTH' => [
            'Elementary Mathematics', 'Calculus', 'Algebra and Trigonometry', 'Introduction to Statistics', 'Computing for Mathematicians',
            'Real Analysis', 'Linear Algebra', 'Differential Equations', 'Vector Analysis', 'Probability Theory',
            'Abstract Algebra', 'Complex Analysis', 'Numerical Analysis', 'Mathematical Methods', 'Operations Research', 'Topology',
            'Functional Analysis', 'Partial Differential Equations', 'Measure Theory', 'Mathematical Modelling', 'Optimization Theory', 'Final Year Project',
        ],
        'PHY' => [
            'General Physics', 'Practical Physics', 'Mathematical Physics', 'Mechanics', 'Introduction to Astronomy',
            'Electricity and Magnetism', 'Waves and Optics', 'Thermal Physics', 'Modern Physics', 'Electronics for Physicists',
            'Quantum Mechanics', 'Electromagnetic Theory', 'Solid State Physics', 'Nuclear Physics', 'Statistical Mechanics', 'Computational Physics',
            'Geophysics', 'Atmospheric Physics', 'Medical Physics', 'Renewable Energy Physics', 'Advanced Laboratory', 'Final Year Project',
        ],
        'ICH' => [
            'General Chemistry', 'Practical Chemistry', 'Organic Chemistry', 'Inorganic Chemistry', 'Physical Chemistry',
            'Analytical Chemistry', 'Chemical Thermodynamics', 'Spectroscopy', 'Industrial Chemical Processes', 'Chemistry of Natural Products',
            'Petroleum Chemistry', 'Polymer Chemistry', 'Chemical Kinetics', 'Environmental Chemistry', 'Unit Operations', 'Quality Control',
            'Petrochemicals', 'Corrosion Science', 'Pharmaceutical Chemistry', 'Industrial Waste Management', 'Process Instrumentation', 'Final Year Project',
        ],
        'ACC' => [
            'Principles of Accounting', 'Introduction to Business', 'Business Mathematics', 'Principles of Economics', 'Business Communication',
            'Financial Accounting', 'Cost Accounting', 'Business Law', 'Quantitative Methods', 'Principles of Management',
            'Management Accounting', 'Taxation', 'Auditing and Assurance', 'Public Sector Accounting', 'Corporate Finance', 'Company Law',
            'Advanced Financial Reporting', 'Forensic Accounting', 'Accounting Information Systems', 'Strategic Management', 'Advanced Taxation', 'Final Year Project',
        ],
        'PMT' => [
            'Introduction to Project Management', 'Principles of Management', 'Business Mathematics', 'Principles of Economics', 'Business Communication',
            'Project Planning and Scheduling', 'Cost Estimation', 'Organisational Behaviour', 'Quantitative Techniques', 'Construction Technology',
            'Project Risk Management', 'Procurement Management', 'Project Finance', 'Operations Management', 'Feasibility Studies', 'Contract Law',
            'Programme and Portfolio Management', 'Project Monitoring and Evaluation', 'Quality Management', 'Human Resource Management', 'Strategic Project Management', 'Final Year Project',
        ],
        'AEC' => [
            'Introduction to Agriculture', 'Principles of Crop Production', 'Principles of Animal Production', 'Introduction to Economics', 'Agricultural Mathematics',
            'Agricultural Microeconomics', 'Farm Management', 'Agricultural Extension', 'Rural Sociology', 'Agricultural Statistics',
            'Agricultural Marketing', 'Agricultural Finance', 'Production Economics', 'Agricultural Policy', 'Resource Economics', 'Research Methods',
            'Agribusiness Management', 'Agricultural Project Appraisal', 'Development Economics', 'Econometrics', 'Food Security Analysis', 'Final Year Project',
        ],
    ];

    /** Units of the n-th course in a semester (the 6th only from 300 level). */
    public const UNITS = [3, 3, 3, 3, 2, 2];

    /** General Studies: [code, title, units, level, semester]. */
    public const GST = [
        ['GST 101', 'Use of English I', 2, 1, 1],
        ['GST 103', 'Nigerian Peoples and Culture', 2, 1, 1],
        ['GST 102', 'Use of English II', 2, 1, 2],
        ['GST 104', 'Use of Library, Study Skills and ICT', 2, 1, 2],
        ['GST 201', 'Philosophy, Logic and Human Existence', 2, 2, 1],
        ['GST 202', 'Entrepreneurship and Innovation', 2, 2, 2],
    ];

    /** Ethnic groups: first names (m/f), surnames, home states with LGAs. */
    public const PEOPLE = [
        'igbo' => [
            'male' => ['Chinedu', 'Emeka', 'Obinna', 'Ifeanyi', 'Chukwuemeka', 'Kelechi', 'Uchenna', 'Nnamdi', 'Ikenna', 'Chidi', 'Somtochukwu', 'Ebuka', 'Tochukwu', 'Chibuzor', 'Kenechukwu', 'Onyekachi'],
            'female' => ['Ngozi', 'Chiamaka', 'Adaeze', 'Nneka', 'Ifeoma', 'Chioma', 'Amarachi', 'Ogechi', 'Ebere', 'Uju', 'Chinenye', 'Somadina', 'Oluchi', 'Nkechi', 'Chidinma', 'Ujunwa'],
            'surnames' => ['Okafor', 'Eze', 'Nwosu', 'Okeke', 'Obi', 'Nwankwo', 'Onyekachi', 'Okonkwo', 'Chukwu', 'Ugwu', 'Nnaji', 'Okoro', 'Anyanwu', 'Nwachukwu', 'Ibe', 'Ezeani', 'Onwuka', 'Agu', 'Ike', 'Mbah'],
            'states' => ['Anambra' => ['Awka South', 'Onitsha North', 'Nnewi North', 'Idemili South'], 'Enugu' => ['Nsukka', 'Enugu North', 'Udi', 'Oji River'], 'Imo' => ['Owerri Municipal', 'Orlu', 'Okigwe', 'Mbaise'], 'Abia' => ['Umuahia North', 'Aba South', 'Ohafia'], 'Ebonyi' => ['Abakaliki', 'Afikpo North', 'Ikwo']],
        ],
        'yoruba' => [
            'male' => ['Oluwaseun', 'Babatunde', 'Adewale', 'Olumide', 'Ayodeji', 'Tunde', 'Segun', 'Femi', 'Damilola', 'Gbenga', 'Kayode', 'Olamide', 'Tobiloba', 'Ademola', 'Opeyemi', 'Ifeoluwa'],
            'female' => ['Funmilayo', 'Adebola', 'Folake', 'Temitope', 'Yetunde', 'Bukola', 'Omolara', 'Titilayo', 'Abimbola', 'Oluwakemi', 'Damilare', 'Morenike', 'Aderonke', 'Simisola', 'Eniola', 'Ayomide'],
            'surnames' => ['Adeyemi', 'Ogunleye', 'Balogun', 'Adebayo', 'Olawale', 'Afolabi', 'Ogundipe', 'Oyelaran', 'Akinola', 'Bakare', 'Adeleke', 'Ojo', 'Fashola', 'Oladipo', 'Adekunle', 'Ilesanmi', 'Ayodele', 'Oyewole', 'Olatunji', 'Ajayi'],
            'states' => ['Oyo' => ['Ibadan North', 'Ogbomosho North', 'Oyo East', 'Iseyin'], 'Ogun' => ['Abeokuta South', 'Ijebu Ode', 'Sagamu', 'Ado-Odo/Ota'], 'Lagos' => ['Ikeja', 'Surulere', 'Epe', 'Ikorodu'], 'Osun' => ['Osogbo', 'Ife Central', 'Ilesa East'], 'Ondo' => ['Akure South', 'Owo', 'Ondo West'], 'Ekiti' => ['Ado-Ekiti', 'Ikere', 'Ijero']],
        ],
        'hausa' => [
            'male' => ['Abubakar', 'Musa', 'Ibrahim', 'Aliyu', 'Usman', 'Sani', 'Bashir', 'Yusuf', 'Abdullahi', 'Kabiru', 'Nasiru', 'Umar', 'Haruna', 'Shehu', 'Lawal', 'Mustapha'],
            'female' => ['Aisha', 'Fatima', 'Hauwa', 'Zainab', 'Amina', 'Halima', 'Maryam', 'Hadiza', 'Rukayya', 'Khadija', 'Safiya', 'Bilkisu', 'Asmau', 'Jamila', 'Zulaihat', 'Sadiya'],
            'surnames' => ['Bello', 'Abdullahi', 'Mohammed', 'Sule', 'Garba', 'Danjuma', 'Yakubu', 'Lawal', 'Usman', 'Aminu', 'Shehu', 'Bala', 'Ahmed', 'Tanko', 'Idris', 'Sambo', 'Gambo', 'Dikko', 'Abba', 'Kabir'],
            'states' => ['Kano' => ['Nassarawa', 'Fagge', 'Dala', 'Gwale'], 'Kaduna' => ['Zaria', 'Kaduna North', 'Kafanchan'], 'Katsina' => ['Katsina', 'Daura', 'Funtua'], 'Sokoto' => ['Sokoto North', 'Wamakko'], 'Bauchi' => ['Bauchi', 'Azare'], 'Niger' => ['Minna', 'Bida', 'Suleja']],
        ],
        'south' => [
            'male' => ['Efe', 'Ese', 'Ekene', 'Tamuno', 'Ibiso', 'Edidiong', 'Aniekan', 'Ubong', 'Oghenekaro', 'Osagie', 'Ebiye', 'Bassey', 'Etim', 'Godwin', 'Kingsley', 'Victor'],
            'female' => ['Ufuoma', 'Edikan', 'Ini', 'Ibiere', 'Oghogho', 'Osaretin', 'Eno', 'Mfon', 'Tari', 'Blessing', 'Grace', 'Mercy', 'Joy', 'Precious', 'Ebiere', 'Itohan'],
            'surnames' => ['Etim', 'Bassey', 'Okon', 'Ekpo', 'Udoh', 'Akpan', 'Ita', 'Edet', 'Omoregie', 'Osagie', 'Iyamu', 'Ighodaro', 'Tamuno', 'George', 'Briggs', 'Amadi', 'Ogbe', 'Emenike', 'Okoh', 'Edem'],
            'states' => ['Akwa Ibom' => ['Uyo', 'Eket', 'Ikot Ekpene'], 'Cross River' => ['Calabar Municipal', 'Ikom', 'Ogoja'], 'Rivers' => ['Port Harcourt', 'Obio/Akpor', 'Bonny'], 'Edo' => ['Oredo', 'Egor', 'Ekpoma'], 'Delta' => ['Warri South', 'Uvwie', 'Asaba'], 'Bayelsa' => ['Yenagoa', 'Brass']],
        ],
        'middlebelt' => [
            'male' => ['Terkaa', 'Ochoche', 'Danladi', 'Samuel', 'Emmanuel', 'Daniel', 'Peter', 'Joseph', 'Iorwuese', 'Sunday', 'Ojonugwa', 'Ayuba', 'Moses', 'Solomon', 'Paul', 'Timothy'],
            'female' => ['Doosuur', 'Mnena', 'Ladi', 'Esther', 'Rebecca', 'Deborah', 'Ruth', 'Comfort', 'Ene', 'Ojoma', 'Hannah', 'Sarah', 'Lydia', 'Naomi', 'Patience', 'Rose'],
            'surnames' => ['Tersoo', 'Akaa', 'Iorliam', 'Ochigbo', 'Agbo', 'Musa', 'Danladi', 'Gyang', 'Pam', 'Dung', 'Adah', 'Onuh', 'Audu', 'Ameh', 'Idoko', 'Ogah', 'Jatau', 'Bitrus', 'Yohanna', 'Gaiya'],
            'states' => ['Benue' => ['Makurdi', 'Gboko', 'Otukpo'], 'Plateau' => ['Jos North', 'Jos South', 'Pankshin'], 'Kogi' => ['Lokoja', 'Idah', 'Anyigba'], 'Nasarawa' => ['Lafia', 'Keffi', 'Akwanga'], 'Kwara' => ['Ilorin West', 'Offa'], 'FCT' => ['Abuja Municipal', 'Gwagwalada', 'Bwari']],
        ],
    ];

    /** Share of each group in the student/staff body. */
    public const MIX = ['igbo' => 30, 'yoruba' => 25, 'hausa' => 18, 'south' => 15, 'middlebelt' => 12];

    public const BANKS = ['First Bank of Nigeria', 'Access Bank', 'Guaranty Trust Bank', 'Zenith Bank', 'United Bank for Africa', 'Fidelity Bank', 'Union Bank', 'Ecobank Nigeria', 'Sterling Bank', 'Wema Bank'];

    /** SIWES employers: [name, address]. */
    public const EMPLOYERS = [
        ['Nigerian National Petroleum Company Ltd', 'NNPC Towers, Herbert Macaulay Way, Abuja'],
        ['MTN Nigeria Communications Plc', 'Golden Plaza, Awolowo Road, Ikoyi, Lagos'],
        ['Dangote Cement Plc', 'Union Marble House, 1 Alfred Rewane Road, Ikoyi, Lagos'],
        ['Transmission Company of Nigeria', 'Plot 441, Zambezi Crescent, Maitama, Abuja'],
        ['Julius Berger Nigeria Plc', '10 Shettima A. Munguno Crescent, Utako, Abuja'],
        ['Interswitch Group', '1648C Oko-Awo Street, Victoria Island, Lagos'],
        ['Shell Petroleum Development Company', 'Industry Road, Rumuobiakani, Port Harcourt'],
        ['Galaxy Backbone Ltd', 'Plot 1, Cadastral Zone, Kukwaba, Abuja'],
        ['Nigerian Breweries Plc', 'Iganmu House, Abebe Village Road, Lagos'],
        ['Eko Electricity Distribution Plc', '24-25 Marina, Lagos Island, Lagos'],
        ['Federal Ministry of Works and Housing', 'Mabushi, Abuja'],
        ['Flour Mills of Nigeria Plc', '2 Old Dock Road, Apapa, Lagos'],
        ['Andela Nigeria', '235 Ikorodu Road, Ilupeju, Lagos'],
        ['Nigerian Communications Commission', 'Plot 423, Aguiyi Ironsi Street, Maitama, Abuja'],
        ['PwC Nigeria', 'Landmark Towers, 5B Water Corporation Road, Victoria Island, Lagos'],
        ['Indorama Eleme Petrochemicals', 'Eleme, Rivers State'],
        ['Olam Nigeria Ltd', 'Plot 11, Ilupeju Industrial Estate, Lagos'],
        ['Kano State Ministry of Agriculture', 'Audu Bako Secretariat, Kano'],
        ['Raw Materials Research and Development Council', 'Plot 427, Aguiyi Ironsi Street, Maitama, Abuja'],
        ['Main One Cable Company', 'Fabac Centre, 3B Ligali Ayorinde Street, Victoria Island, Lagos'],
    ];

    /** Non-academic posts: [designation, role or null, grade level, count]. */
    public const NON_ACADEMIC = [
        ['Deputy Registrar (Academic Affairs)', null, 15, 1],
        ['Deputy Bursar', null, 15, 1],
        ['Director, ICT Centre', null, 15, 1],
        ['Chief Accountant', null, 14, 1],
        ['Senior Accountant', null, 12, 2],
        ['Principal Administrative Officer', null, 13, 2],
        ['Administrative Officer', null, 9, 5],
        ['Executive Officer', null, 7, 5],
        ['Library Officer', null, 8, 3],
        ['Hall Warden', null, 9, 4],
        ['Principal Technologist', null, 12, 3],
        ['Laboratory Technologist', null, 9, 5],
        ['Confidential Secretary', null, 8, 3],
        ['Chief Security Officer', null, 12, 1],
        ['Security Officer', null, 6, 2],
        ['Driver', null, 4, 2],
    ];

    public const LEAVE_REASONS = [
        'annual' => ['Annual vacation with family', 'Annual leave', 'Rest and family visit'],
        'sick' => ['Medical treatment', 'Recovery after surgery', 'Malaria treatment and rest'],
        'casual' => ['Family wedding', 'Burial ceremony in hometown', 'Child dedication'],
        'study' => ['PhD thesis defence', 'Conference attendance abroad', 'Professional certification exams'],
        'maternity' => ['Maternity leave'],
    ];
}
