<?php

namespace App\Support;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * ESTADOS Y CIUDADES DE ESTADOS UNIDOS
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Para que nadie tenga que teclear "Miami" ochenta veces al mes, ni
 * escribirlo mal la vez ochenta y uno.
 *
 * ── POR QUÉ UN ARCHIVO Y NO UNA TABLA ──
 *
 * Porque no cambia. Las ciudades de Florida son las mismas hoy que hace
 * diez años. Una tabla obligaría a una migración, un seeder y una
 * consulta en cada renglón de dirección del formulario, a cambio de
 * poder editar algo que nadie va a editar.
 *
 * El día que haga falta agregar una ciudad, se agrega una línea aquí.
 *
 * ── LA LISTA NO ES UNA CÁRCEL ──
 *
 * El campo de ciudad acepta cualquier texto. La lista solo sugiere. Si
 * el cliente vive en un pueblo que no está, se escribe y ya: el sistema
 * lo guarda igual.
 *
 * Eso es a propósito. Una lista cerrada de ciudades es la clase de
 * decisión que parece prolija hasta el día que un cliente real no entra
 * en ella.
 *
 * ── LA COBERTURA ──
 *
 * FLORIDA va completa, o casi: es donde está el negocio y donde van a
 * caer nueve de cada diez direcciones.
 *
 * Los estados vecinos —Georgia, Alabama, las dos Carolinas— van con sus
 * ciudades principales, que es de donde viene el resto del trabajo.
 *
 * El resto del país va con sus ciudades grandes. Suficiente para que el
 * buscador sirva, sin convertir esto en un censo.
 * ═══════════════════════════════════════════════════════════════════════════
 */
class UsPlaces
{
    /**
     * Los estados, por su código de dos letras.
     *
     * Incluye DC y los territorios: Puerto Rico aparece en las
     * direcciones de este negocio más de lo que uno esperaría.
     */
    public const ESTADOS = [
        'AL' => 'Alabama',
        'AK' => 'Alaska',
        'AZ' => 'Arizona',
        'AR' => 'Arkansas',
        'CA' => 'California',
        'CO' => 'Colorado',
        'CT' => 'Connecticut',
        'DE' => 'Delaware',
        'DC' => 'District of Columbia',
        'FL' => 'Florida',
        'GA' => 'Georgia',
        'HI' => 'Hawaii',
        'ID' => 'Idaho',
        'IL' => 'Illinois',
        'IN' => 'Indiana',
        'IA' => 'Iowa',
        'KS' => 'Kansas',
        'KY' => 'Kentucky',
        'LA' => 'Louisiana',
        'ME' => 'Maine',
        'MD' => 'Maryland',
        'MA' => 'Massachusetts',
        'MI' => 'Michigan',
        'MN' => 'Minnesota',
        'MS' => 'Mississippi',
        'MO' => 'Missouri',
        'MT' => 'Montana',
        'NE' => 'Nebraska',
        'NV' => 'Nevada',
        'NH' => 'New Hampshire',
        'NJ' => 'New Jersey',
        'NM' => 'New Mexico',
        'NY' => 'New York',
        'NC' => 'North Carolina',
        'ND' => 'North Dakota',
        'OH' => 'Ohio',
        'OK' => 'Oklahoma',
        'OR' => 'Oregon',
        'PA' => 'Pennsylvania',
        'RI' => 'Rhode Island',
        'SC' => 'South Carolina',
        'SD' => 'South Dakota',
        'TN' => 'Tennessee',
        'TX' => 'Texas',
        'UT' => 'Utah',
        'VT' => 'Vermont',
        'VA' => 'Virginia',
        'WA' => 'Washington',
        'WV' => 'West Virginia',
        'WI' => 'Wisconsin',
        'WY' => 'Wyoming',
        'PR' => 'Puerto Rico',
        'VI' => 'U.S. Virgin Islands',
        'GU' => 'Guam',
    ];

    /**
     * Las ciudades de cada estado.
     *
     * Ordenadas alfabéticamente para que la lista se lea, no por
     * población: quien busca "Naples" lo busca por la N.
     */
    public const CIUDADES = [

        /* ─────────────────────────────────────────────────────────────
           FLORIDA — la lista larga, que es la que se usa todos los días
        ───────────────────────────────────────────────────────────── */
        'FL' => [
            'Alachua', 'Altamonte Springs', 'Apopka', 'Arcadia', 'Atlantic Beach',
            'Auburndale', 'Aventura', 'Avon Park', 'Bal Harbour', 'Bartow',
            'Bay Harbor Islands', 'Belle Glade', 'Belleair', 'Beverly Hills',
            'Boca Raton', 'Bonita Springs', 'Boynton Beach', 'Bradenton',
            'Brandon', 'Brooksville', 'Bunnell', 'Cape Canaveral', 'Cape Coral',
            'Casselberry', 'Celebration', 'Chiefland', 'Chipley', 'Clearwater',
            'Clermont', 'Clewiston', 'Cocoa', 'Cocoa Beach', 'Coconut Creek',
            'Coleman', 'Cooper City', 'Coral Gables', 'Coral Springs',
            'Crestview', 'Crystal River', 'Cutler Bay', 'Dade City', 'Dania Beach',
            'Davenport', 'Davie', 'Daytona Beach', 'DeBary', 'Deerfield Beach',
            'DeFuniak Springs', 'DeLand', 'Delray Beach', 'Deltona', 'Destin',
            'Doral', 'Dunedin', 'Dunnellon', 'Eagle Lake', 'Edgewater',
            'Estero', 'Eustis', 'Fernandina Beach', 'Flagler Beach',
            'Fort Lauderdale', 'Fort Myers', 'Fort Myers Beach', 'Fort Pierce',
            'Fort Walton Beach', 'Fruitland Park', 'Gainesville', 'Golden Beach',
            'Green Cove Springs', 'Greenacres', 'Groveland', 'Gulf Breeze',
            'Gulfport', 'Haines City', 'Hallandale Beach', 'Hialeah',
            'Hialeah Gardens', 'Highland Beach', 'Hobe Sound', 'Holly Hill',
            'Hollywood', 'Homestead', 'Hudson', 'Immokalee', 'Indialantic',
            'Inverness', 'Islamorada', 'Jacksonville', 'Jacksonville Beach',
            'Jasper', 'Jensen Beach', 'Jupiter', 'Key Biscayne', 'Key Largo',
            'Key West', 'Kissimmee', 'LaBelle', 'Lady Lake', 'Lake City',
            'Lake Mary', 'Lake Park', 'Lake Wales', 'Lake Worth Beach',
            'Lakeland', 'Land O Lakes', 'Largo', 'Lauderdale Lakes',
            'Lauderhill', 'Leesburg', 'Lehigh Acres', 'Lighthouse Point',
            'Live Oak', 'Longboat Key', 'Longwood', 'Lutz', 'Lynn Haven',
            'Macclenny', 'Madison', 'Maitland', 'Marathon', 'Marco Island',
            'Margate', 'Marianna', 'Melbourne', 'Merritt Island', 'Miami',
            'Miami Beach', 'Miami Gardens', 'Miami Lakes', 'Miami Springs',
            'Milton', 'Miramar', 'Mount Dora', 'Naples', 'Navarre',
            'New Port Richey', 'New Smyrna Beach', 'Newberry', 'Niceville',
            'North Bay Village', 'North Fort Myers', 'North Lauderdale',
            'North Miami', 'North Miami Beach', 'North Palm Beach',
            'North Port', 'Oakland Park', 'Ocala', 'Ocoee', 'Okeechobee',
            'Oldsmar', 'Opa-locka', 'Orange City', 'Orange Park', 'Orlando',
            'Ormond Beach', 'Oviedo', 'Palatka', 'Palm Bay', 'Palm Beach',
            'Palm Beach Gardens', 'Palm Coast', 'Palm Harbor', 'Palm Springs',
            'Palmetto', 'Palmetto Bay', 'Panama City', 'Panama City Beach',
            'Parkland', 'Pembroke Pines', 'Pensacola', 'Perry', 'Pinecrest',
            'Pinellas Park', 'Plant City', 'Plantation', 'Pompano Beach',
            'Ponte Vedra Beach', 'Port Charlotte', 'Port Orange',
            'Port St. Lucie', 'Punta Gorda', 'Quincy', 'Riviera Beach',
            'Rockledge', 'Royal Palm Beach', 'Ruskin', 'Safety Harbor',
            'San Antonio', 'Sanford', 'Sanibel', 'Sarasota', 'Satellite Beach',
            'Sebastian', 'Sebring', 'Seminole', 'Sneads', 'South Miami',
            'Spring Hill', 'St. Augustine', 'St. Cloud', 'St. Pete Beach',
            'St. Petersburg', 'Starke', 'Stuart', 'Sunny Isles Beach',
            'Sunrise', 'Surfside', 'Sweetwater', 'Tallahassee', 'Tamarac',
            'Tampa', 'Tarpon Springs', 'Tavares', 'Temple Terrace',
            'Tequesta', 'Titusville', 'Trenton', 'Valparaiso', 'Venice',
            'Vero Beach', 'Wauchula', 'Weeki Wachee', 'Wellington',
            'Wesley Chapel', 'West Melbourne', 'West Palm Beach', 'Weston',
            'Wildwood', 'Williston', 'Wilton Manors', 'Windermere',
            'Winter Garden', 'Winter Haven', 'Winter Park', 'Winter Springs',
            'Zephyrhills',
        ],

        /* ─────────────────────────────────────────────────────────────
           LOS VECINOS — de donde viene el resto del trabajo
        ───────────────────────────────────────────────────────────── */
        'GA' => [
            'Albany', 'Alpharetta', 'Athens', 'Atlanta', 'Augusta', 'Brunswick',
            'Columbus', 'Dalton', 'Decatur', 'Douglasville', 'Dunwoody',
            'East Point', 'Gainesville', 'Hinesville', 'Johns Creek', 'Kennesaw',
            'Lawrenceville', 'Macon', 'Marietta', 'Newnan', 'Peachtree City',
            'Roswell', 'Sandy Springs', 'Savannah', 'Smyrna', 'Statesboro',
            'Valdosta', 'Warner Robins', 'Waycross',
        ],
        'AL' => [
            'Anniston', 'Auburn', 'Birmingham', 'Decatur', 'Dothan', 'Enterprise',
            'Florence', 'Gadsden', 'Hoover', 'Huntsville', 'Madison', 'Mobile',
            'Montgomery', 'Opelika', 'Phenix City', 'Prattville', 'Tuscaloosa',
        ],
        'SC' => [
            'Aiken', 'Anderson', 'Beaufort', 'Charleston', 'Columbia', 'Conway',
            'Florence', 'Goose Creek', 'Greenville', 'Hilton Head Island',
            'Mount Pleasant', 'Myrtle Beach', 'North Charleston', 'Rock Hill',
            'Spartanburg', 'Summerville', 'Sumter',
        ],
        'NC' => [
            'Asheville', 'Burlington', 'Cary', 'Chapel Hill', 'Charlotte',
            'Concord', 'Durham', 'Fayetteville', 'Gastonia', 'Greensboro',
            'Greenville', 'High Point', 'Huntersville', 'Jacksonville',
            'Kannapolis', 'Raleigh', 'Rocky Mount', 'Wilmington', 'Winston-Salem',
        ],

        /* ─────────────────────────────────────────────────────────────
           EL RESTO — las ciudades grandes de cada estado
        ───────────────────────────────────────────────────────────── */
        'AK' => ['Anchorage', 'Fairbanks', 'Juneau', 'Ketchikan', 'Sitka', 'Wasilla'],
        'AZ' => ['Avondale', 'Chandler', 'Flagstaff', 'Gilbert', 'Glendale', 'Goodyear',
                 'Mesa', 'Peoria', 'Phoenix', 'Scottsdale', 'Surprise', 'Tempe',
                 'Tucson', 'Yuma'],
        'AR' => ['Bentonville', 'Conway', 'Fayetteville', 'Fort Smith', 'Hot Springs',
                 'Jonesboro', 'Little Rock', 'North Little Rock', 'Rogers', 'Springdale'],
        'CA' => ['Anaheim', 'Bakersfield', 'Berkeley', 'Chula Vista', 'Fontana',
                 'Fremont', 'Fresno', 'Glendale', 'Irvine', 'Long Beach',
                 'Los Angeles', 'Modesto', 'Moreno Valley', 'Oakland', 'Oxnard',
                 'Riverside', 'Sacramento', 'Salinas', 'San Bernardino', 'San Diego',
                 'San Francisco', 'San Jose', 'Santa Ana', 'Santa Clarita',
                 'Santa Rosa', 'Stockton', 'Sunnyvale'],
        'CO' => ['Arvada', 'Aurora', 'Boulder', 'Centennial', 'Colorado Springs',
                 'Denver', 'Fort Collins', 'Greeley', 'Lakewood', 'Longmont',
                 'Pueblo', 'Thornton', 'Westminster'],
        'CT' => ['Bridgeport', 'Bristol', 'Danbury', 'Hartford', 'Meriden',
                 'New Britain', 'New Haven', 'Norwalk', 'Stamford', 'Waterbury',
                 'West Hartford'],
        'DE' => ['Dover', 'Middletown', 'Newark', 'Rehoboth Beach', 'Smyrna', 'Wilmington'],
        'DC' => ['Washington'],
        'HI' => ['Hilo', 'Honolulu', 'Kahului', 'Kailua', 'Kaneohe', 'Kapolei', 'Pearl City'],
        'ID' => ['Boise', 'Coeur d Alene', 'Idaho Falls', 'Meridian', 'Nampa',
                 'Pocatello', 'Twin Falls'],
        'IL' => ['Arlington Heights', 'Aurora', 'Bloomington', 'Champaign', 'Chicago',
                 'Cicero', 'Decatur', 'Elgin', 'Evanston', 'Joliet', 'Naperville',
                 'Peoria', 'Rockford', 'Schaumburg', 'Springfield', 'Waukegan'],
        'IN' => ['Bloomington', 'Carmel', 'Evansville', 'Fishers', 'Fort Wayne',
                 'Gary', 'Hammond', 'Indianapolis', 'Lafayette', 'Muncie',
                 'Noblesville', 'South Bend', 'Terre Haute'],
        'IA' => ['Ames', 'Ankeny', 'Cedar Falls', 'Cedar Rapids', 'Council Bluffs',
                 'Davenport', 'Des Moines', 'Dubuque', 'Iowa City', 'Sioux City',
                 'Waterloo', 'West Des Moines'],
        'KS' => ['Hutchinson', 'Kansas City', 'Lawrence', 'Lenexa', 'Manhattan',
                 'Olathe', 'Overland Park', 'Salina', 'Shawnee', 'Topeka', 'Wichita'],
        'KY' => ['Bowling Green', 'Covington', 'Elizabethtown', 'Florence',
                 'Frankfort', 'Georgetown', 'Henderson', 'Hopkinsville', 'Lexington',
                 'Louisville', 'Owensboro', 'Paducah', 'Richmond'],
        'LA' => ['Alexandria', 'Baton Rouge', 'Bossier City', 'Houma', 'Kenner',
                 'Lafayette', 'Lake Charles', 'Metairie', 'Monroe', 'New Iberia',
                 'New Orleans', 'Shreveport', 'Slidell'],
        'ME' => ['Augusta', 'Auburn', 'Bangor', 'Biddeford', 'Lewiston', 'Portland',
                 'Saco', 'South Portland'],
        'MD' => ['Annapolis', 'Baltimore', 'Bethesda', 'Bowie', 'College Park',
                 'Columbia', 'Frederick', 'Gaithersburg', 'Hagerstown', 'Rockville',
                 'Salisbury', 'Silver Spring', 'Towson'],
        'MA' => ['Boston', 'Brockton', 'Cambridge', 'Fall River', 'Framingham',
                 'Lawrence', 'Lowell', 'Lynn', 'Medford', 'New Bedford', 'Newton',
                 'Quincy', 'Somerville', 'Springfield', 'Waltham', 'Worcester'],
        'MI' => ['Ann Arbor', 'Battle Creek', 'Dearborn', 'Detroit', 'Farmington Hills',
                 'Flint', 'Grand Rapids', 'Kalamazoo', 'Lansing', 'Livonia',
                 'Novi', 'Rochester Hills', 'Royal Oak', 'Saginaw', 'Southfield',
                 'Sterling Heights', 'Troy', 'Warren', 'Westland'],
        'MN' => ['Bloomington', 'Brooklyn Park', 'Duluth', 'Eagan', 'Eden Prairie',
                 'Lakeville', 'Maple Grove', 'Minneapolis', 'Minnetonka', 'Plymouth',
                 'Rochester', 'St. Cloud', 'St. Paul', 'Woodbury'],
        'MS' => ['Biloxi', 'Columbus', 'Greenville', 'Gulfport', 'Hattiesburg',
                 'Jackson', 'Meridian', 'Olive Branch', 'Pascagoula', 'Southaven',
                 'Starkville', 'Tupelo'],
        'MO' => ['Blue Springs', 'Columbia', 'Independence', 'Jefferson City',
                 'Joplin', 'Kansas City', 'Lees Summit', "O'Fallon", 'Springfield',
                 'St. Charles', 'St. Joseph', 'St. Louis', 'St. Peters'],
        'MT' => ['Billings', 'Bozeman', 'Butte', 'Great Falls', 'Helena',
                 'Kalispell', 'Missoula'],
        'NE' => ['Bellevue', 'Fremont', 'Grand Island', 'Hastings', 'Kearney',
                 'Lincoln', 'Norfolk', 'North Platte', 'Omaha', 'Papillion'],
        'NV' => ['Carson City', 'Elko', 'Henderson', 'Las Vegas', 'North Las Vegas',
                 'Pahrump', 'Reno', 'Sparks'],
        'NH' => ['Concord', 'Derry', 'Dover', 'Keene', 'Manchester', 'Nashua',
                 'Portsmouth', 'Rochester', 'Salem'],
        'NJ' => ['Atlantic City', 'Bayonne', 'Camden', 'Cherry Hill', 'Clifton',
                 'East Orange', 'Edison', 'Elizabeth', 'Hoboken', 'Jersey City',
                 'Newark', 'Passaic', 'Paterson', 'Toms River', 'Trenton',
                 'Union City', 'Vineland', 'Woodbridge'],
        'NM' => ['Alamogordo', 'Albuquerque', 'Carlsbad', 'Clovis', 'Farmington',
                 'Hobbs', 'Las Cruces', 'Rio Rancho', 'Roswell', 'Santa Fe'],
        'NY' => ['Albany', 'Binghamton', 'Brooklyn', 'Buffalo', 'Hempstead',
                 'Ithaca', 'Mount Vernon', 'New Rochelle', 'New York', 'Niagara Falls',
                 'Poughkeepsie', 'Queens', 'Rochester', 'Schenectady', 'Staten Island',
                 'Syracuse', 'Troy', 'Utica', 'White Plains', 'Yonkers'],
        'ND' => ['Bismarck', 'Dickinson', 'Fargo', 'Grand Forks', 'Jamestown',
                 'Mandan', 'Minot', 'West Fargo', 'Williston'],
        'OH' => ['Akron', 'Canton', 'Cincinnati', 'Cleveland', 'Columbus', 'Dayton',
                 'Dublin', 'Elyria', 'Hamilton', 'Kettering', 'Lakewood', 'Lorain',
                 'Mansfield', 'Parma', 'Springfield', 'Toledo', 'Youngstown'],
        'OK' => ['Broken Arrow', 'Edmond', 'Enid', 'Lawton', 'Midwest City',
                 'Moore', 'Muskogee', 'Norman', 'Oklahoma City', 'Stillwater', 'Tulsa'],
        'OR' => ['Albany', 'Beaverton', 'Bend', 'Corvallis', 'Eugene', 'Gresham',
                 'Hillsboro', 'Medford', 'Portland', 'Salem', 'Springfield', 'Tigard'],
        'PA' => ['Allentown', 'Altoona', 'Bethlehem', 'Chester', 'Erie', 'Harrisburg',
                 'Lancaster', 'Levittown', 'Philadelphia', 'Pittsburgh', 'Reading',
                 'Scranton', 'State College', 'Wilkes-Barre', 'York'],
        'RI' => ['Cranston', 'East Providence', 'Newport', 'Pawtucket', 'Providence',
                 'Warwick', 'Woonsocket'],
        'SD' => ['Aberdeen', 'Brookings', 'Mitchell', 'Pierre', 'Rapid City',
                 'Sioux Falls', 'Watertown', 'Yankton'],
        'TN' => ['Bartlett', 'Chattanooga', 'Clarksville', 'Cleveland', 'Collierville',
                 'Franklin', 'Hendersonville', 'Jackson', 'Johnson City', 'Kingsport',
                 'Knoxville', 'Memphis', 'Murfreesboro', 'Nashville', 'Smyrna'],
        'TX' => ['Abilene', 'Amarillo', 'Arlington', 'Austin', 'Beaumont', 'Brownsville',
                 'Carrollton', 'College Station', 'Corpus Christi', 'Dallas',
                 'Denton', 'Edinburg', 'El Paso', 'Fort Worth', 'Frisco',
                 'Garland', 'Grand Prairie', 'Houston', 'Irving', 'Killeen',
                 'Laredo', 'League City', 'Lewisville', 'Lubbock', 'McAllen',
                 'McKinney', 'Mesquite', 'Midland', 'Odessa', 'Pasadena',
                 'Pearland', 'Plano', 'Richardson', 'Round Rock', 'San Angelo',
                 'San Antonio', 'Sugar Land', 'Temple', 'Tyler', 'Waco',
                 'Wichita Falls'],
        'UT' => ['Bountiful', 'Draper', 'Layton', 'Lehi', 'Logan', 'Ogden', 'Orem',
                 'Provo', 'Salt Lake City', 'Sandy', 'South Jordan', 'St. George',
                 'West Jordan', 'West Valley City'],
        'VT' => ['Barre', 'Bennington', 'Brattleboro', 'Burlington', 'Montpelier',
                 'Rutland', 'South Burlington'],
        'VA' => ['Alexandria', 'Arlington', 'Charlottesville', 'Chesapeake',
                 'Danville', 'Fredericksburg', 'Hampton', 'Harrisonburg',
                 'Lynchburg', 'Manassas', 'Newport News', 'Norfolk', 'Portsmouth',
                 'Richmond', 'Roanoke', 'Suffolk', 'Virginia Beach', 'Winchester'],
        'WA' => ['Auburn', 'Bellevue', 'Bellingham', 'Everett', 'Federal Way',
                 'Kennewick', 'Kent', 'Kirkland', 'Olympia', 'Pasco', 'Redmond',
                 'Renton', 'Seattle', 'Spokane', 'Tacoma', 'Vancouver', 'Yakima'],
        'WV' => ['Beckley', 'Charleston', 'Clarksburg', 'Fairmont', 'Huntington',
                 'Martinsburg', 'Morgantown', 'Parkersburg', 'Wheeling'],
        'WI' => ['Appleton', 'Eau Claire', 'Fond du Lac', 'Green Bay', 'Janesville',
                 'Kenosha', 'La Crosse', 'Madison', 'Milwaukee', 'Oshkosh',
                 'Racine', 'Sheboygan', 'Waukesha', 'Wausau', 'West Allis'],
        'WY' => ['Casper', 'Cheyenne', 'Gillette', 'Green River', 'Jackson',
                 'Laramie', 'Rock Springs', 'Sheridan'],

        'PR' => ['Aguadilla', 'Arecibo', 'Bayamón', 'Caguas', 'Carolina', 'Cayey',
                 'Fajardo', 'Guaynabo', 'Humacao', 'Mayagüez', 'Ponce',
                 'San Juan', 'Toa Baja', 'Trujillo Alto', 'Vega Baja'],
        'VI' => ['Charlotte Amalie', 'Christiansted', 'Frederiksted'],
        'GU' => ['Dededo', 'Hagåtña', 'Tamuning', 'Yigo'],
    ];

    /**
     * Los códigos de país del teléfono.
     *
     * Estados Unidos primero, después los países de donde vienen los
     * clientes y los proveedores de este negocio, y después el resto en
     * orden alfabético.
     *
     * No están los 195 países del mundo a propósito: una lista de 195
     * opciones para elegir "+1" el 95% de las veces es peor que una de
     * treinta.
     */
    public const PAISES_TELEFONO = [
        ['codigo' => '+1',  'pais' => 'Estados Unidos / Canadá', 'iso' => 'US'],
        ['codigo' => '+52', 'pais' => 'México',                  'iso' => 'MX'],
        ['codigo' => '+58', 'pais' => 'Venezuela',               'iso' => 'VE'],
        ['codigo' => '+57', 'pais' => 'Colombia',                'iso' => 'CO'],
        ['codigo' => '+53', 'pais' => 'Cuba',                    'iso' => 'CU'],
        ['codigo' => '+509','pais' => 'Haití',                   'iso' => 'HT'],
        ['codigo' => '+1809','pais' => 'República Dominicana',   'iso' => 'DO'],
        ['codigo' => '+504','pais' => 'Honduras',                'iso' => 'HN'],
        ['codigo' => '+502','pais' => 'Guatemala',               'iso' => 'GT'],
        ['codigo' => '+503','pais' => 'El Salvador',             'iso' => 'SV'],
        ['codigo' => '+505','pais' => 'Nicaragua',               'iso' => 'NI'],
        ['codigo' => '+506','pais' => 'Costa Rica',              'iso' => 'CR'],
        ['codigo' => '+507','pais' => 'Panamá',                  'iso' => 'PA'],
        ['codigo' => '+51', 'pais' => 'Perú',                    'iso' => 'PE'],
        ['codigo' => '+593','pais' => 'Ecuador',                 'iso' => 'EC'],
        ['codigo' => '+56', 'pais' => 'Chile',                   'iso' => 'CL'],
        ['codigo' => '+54', 'pais' => 'Argentina',               'iso' => 'AR'],
        ['codigo' => '+55', 'pais' => 'Brasil',                  'iso' => 'BR'],
        ['codigo' => '+591','pais' => 'Bolivia',                 'iso' => 'BO'],
        ['codigo' => '+595','pais' => 'Paraguay',                'iso' => 'PY'],
        ['codigo' => '+598','pais' => 'Uruguay',                 'iso' => 'UY'],
        ['codigo' => '+34', 'pais' => 'España',                  'iso' => 'ES'],
        ['codigo' => '+44', 'pais' => 'Reino Unido',             'iso' => 'GB'],
        ['codigo' => '+49', 'pais' => 'Alemania',                'iso' => 'DE'],
        ['codigo' => '+33', 'pais' => 'Francia',                 'iso' => 'FR'],
        ['codigo' => '+39', 'pais' => 'Italia',                  'iso' => 'IT'],
        ['codigo' => '+31', 'pais' => 'Países Bajos',            'iso' => 'NL'],
        ['codigo' => '+86', 'pais' => 'China',                   'iso' => 'CN'],
        ['codigo' => '+91', 'pais' => 'India',                   'iso' => 'IN'],
        ['codigo' => '+82', 'pais' => 'Corea del Sur',           'iso' => 'KR'],
        ['codigo' => '+81', 'pais' => 'Japón',                   'iso' => 'JP'],
    ];

    /**
     * Los dominios de correo que aparecen en el desplegable.
     *
     * Gmail primero porque es el que usa media Florida. Los tres de
     * Microsoft van juntos. `icloud` está porque en el sur de Florida
     * hay mucho iPhone.
     */
    public const DOMINIOS_CORREO = [
        'gmail.com',
        'hotmail.com',
        'outlook.com',
        'live.com',
        'yahoo.com',
        'icloud.com',
        'aol.com',
        'msn.com',
        'comcast.net',
        'bellsouth.net',
        'att.net',
        'me.com',
        'protonmail.com',
    ];

    /* =====================================================================
     | AYUDAS
     * ================================================================== */

    /** Las ciudades de un estado. Vacío si el estado no está en la lista. */
    public static function ciudadesDe(?string $estado): array
    {
        return self::CIUDADES[strtoupper((string) $estado)] ?? [];
    }

    /**
     * Todas las ciudades, sin repetir.
     *
     * Para cuando todavía no se eligió estado: la lista sugiere igual, y
     * quien escribe "Homestead" lo encuentra sin haber tocado el estado
     * primero.
     */
    public static function todasLasCiudades(): array
    {
        $todas = array_merge(...array_values(self::CIUDADES));

        sort($todas);

        return array_values(array_unique($todas));
    }

    /** Para llenar un <select> de estados: ['FL' => 'Florida (FL)', ...] */
    public static function estadosParaSelect(): array
    {
        $salida = [];

        foreach (self::ESTADOS as $codigo => $nombre) {
            $salida[$codigo] = $nombre.' ('.$codigo.')';
        }

        return $salida;
    }
}
