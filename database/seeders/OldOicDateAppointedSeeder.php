<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeePosition;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OldOicDateAppointedSeeder extends Seeder
{
    private array $rows = [
        ['BOLIMA, JOEL RAMIREZ', 'Head Cook (OIC)', 'Ali Mall', 'August 26, 2023'],
        ['TOMENIO, JOAN MISA', 'Restaurant Manager 1 (OIC)', 'Ali Mall', ''],
        ['ARCEÑO, JULIETH BANCORO', 'Assistant Restaurant Manager (OIC)', 'Ayala Arca South', 'June 26, 2022'],
        ['LAGANAS, EDWIN TENORIO', 'Head Cook (OIC)', 'Ayala Arca South', 'June 01, 2016'],
        ['DELA CRUZ, RENALYN NAVARRA', 'Assistant Restaurant Manager (OIC)', 'Ayala Bay Area', 'November 26, 2024'],
        ['PASTRANA, EROLL LEYSON', 'Head Cook (OIC)', 'Ayala Bay Area', 'December 01, 2016'],
        ['PREDAS, JERSON TABORA', 'Head Cook (OIC)', 'Ayala Fairview Terraces', 'December 01, 2014'],
        ['DE VERA, JUNIOR ROCIENTO', 'Assistant Head Cook (OIC)', 'Ayala Pavilion Mall', 'March 01, 2026'],
        ['CATINA, MARIGRACE PALERMO', 'Assistant Restaurant Manager (OIC)', 'Ayala Pavilion Mall', 'September 26, 2024'],
        ['MORENO, MANUEL LOUIS BALINO', 'Assistant Head Cook (OIC)', 'Ayala Serin', 'November 26, 2023'],
        ['GATDULA, SHEILA MAE MOJICA', 'Assistant Restaurant Manager (OIC)', 'Ayala Serin', 'September 11, 2024'],
        ['ALEJANDRE, KATRINA CAMILLE WAQUIZ', 'Assistant Restaurant Manager (OIC)', 'Ayala Solenad 3', 'September 11, 2024'],
        ['BARIA, REY FELICES', 'Head Cook (OIC)', 'Ayala Solenad 3', 'May 01, 2018'],
        ['COMABRAS, FLOREN MERCADO', 'Assistant Head Cook (OIC)', 'Ayala The District Imus', 'April 01, 2025'],
        ['GALBAN, RUSSELL HIDALGO', 'Restaurant Manager 1 (OIC)', 'Ayala The District Imus', ''],
        ['QUIPIT JR., MARIO LERAZAN', 'Assistant Head Cook (OIC)', 'Ayala Tutuban Center', 'March 15, 2024'],
        ['MATA, LYKA MARIE BACULPO', 'Shift Manager (OIC)', 'Ayala Tutuban Center', 'August 01, 2025'],
        ['MAGLALANG JR., DANILO YAMOG', 'Assistant Head Cook (OIC)', 'Ayala Vermosa', 'March 01, 2024'],
        ['RAPAL, ERLIE CALASTRE', 'Assistant Head Cook (OIC)', 'Cash & Carry Mall', 'October 26, 2025'],
        ['CUSI, JUDELAINNE VILLAMOR', 'Restaurant Manager 1 (OIC)', 'Cash & Carry Mall', 'September 11, 2023'],
        ['MATUGUINAS, JAYSON SEPE', 'Assistant Head Cook (OIC)', 'City Place Square', 'January 26, 2026'],
        ['HERNANDEZ, GECYL GARCIA', 'Assistant Restaurant Manager (OIC)', 'City Place Square', 'April 26, 2024'],
        ['CASTILLO, JENIFER ABOGANDA', 'Assistant Head Cook (OIC)', 'Commissary Warehouse', 'February 11, 2025'],
        ['BENAVIDEZ, JERALD VALEZA', 'Assistant Head Cook (OIC)', 'Commissary Warehouse', 'March 01, 2025'],
        ['REYES, RAYMOND PAGSUYUIN', 'Assistant Head Cook (OIC)', 'Festival Mall', 'March 01, 2025'],
        ['TIGLAO, ALLAN ANIVES', 'Assistant Restaurant Manager (OIC)', 'Festival Mall', 'July 01, 2024'],
        ['GESITE, BERNARD AZURA', 'Assistant Head Cook (OIC)', 'Fisher Mall', 'December 02, 2024'],
        ['DUCANES, RHEO LOZADA', 'Head Cook (OIC)', 'Fisher Mall', 'February 01, 2018'],
        ['LEJARDE, JASPER KENNETH JUSTO', 'Shift Manager (OIC)', 'Fisher Mall', 'March 08, 2026'],
        ['REVILLAME, REYNOLD DEL VALLE', 'Assistant Head Cook (OIC)', 'Gaisano Mall Davao', 'August 11, 2023'],
        ['VILLAFAÑE, FRANCIS VALDEZ', 'Head Cook (OIC)', 'Greenhills 2', 'April 15, 2013'],
        ['ZUÑIGA, MICHELLE ESGUERRA', 'Assistant Restaurant Manager (OIC)', 'Market Market', 'April 11, 2024'],
        ['SUECO JR., CARLITO FLORES', 'Head Cook (OIC)', 'Market Market', 'November 15, 2019'],
        ['RIVAS JR., AVELINO DACALCAP', 'Assistant Head Cook (OIC)', 'Mckinley Hills', 'July 01, 2023'],
        ['BASABE, JELYN MANUALES', 'Restaurant Manager 2 (OIC)', 'Mckinley Hills', 'December 22, 2018'],
        ['IGUP, OLIVER ORTEGA', 'Assistant Head Cook (OIC)', 'New Gateway Mall Ii', 'February 26, 2025'],
        ['CINCO, MICHELLE OLO', 'Shift Manager (OIC)', 'New Gateway Mall Ii', 'April 06, 2025'],
        ['MALAYAS, EDMAR SITJAR', 'Assistant Head Cook (OIC)', 'Robinsons Antipolo', 'March 11, 2024'],
        ['PEPILLO, JOSEPH CORDOVA', 'Head Cook (OIC)', 'Robinsons Antipolo', 'November 01, 2018'],
        ['ODIADA JR., ROBERT VILLANUEVA', 'Restaurant Manager 1 (OIC)', 'Robinsons Antipolo', 'August 11, 2022'],
        ['BAUTISTA, MARVIN VERDERA', 'Assistant Head Cook (OIC)', 'Robinsons Dasmariñas', 'March 02, 2026'],
        ['TIEMSIM, FLORANTE MARTINEZ', 'Assistant Head Cook (OIC)', 'Robinsons Ermita', 'June 01, 2022'],
        ['PANCUBIT, AENNA COTONER', 'Assistant Restaurant Manager (OIC)', 'Robinsons Ermita', 'September 11, 2025'],
        ['WAGAN, ALBERT MALABAG', 'Head Cook (OIC)', 'Robinsons Ermita', 'July 11, 2019'],
        ['FERNANDEZ, VIRGILIO CAYETANO', 'Assistant Head Cook (OIC)', 'Robinsons Galleria Ortigas', 'March 11, 2026'],
        ['PEREZ, IREN SALAZAR', 'Assistant Restaurant Manager (OIC)', 'Robinsons Galleria Ortigas', 'February 15, 2025'],
        ['RAMOS, NIÑA CARLA SACAYANAN', 'Shift Manager (OIC)', 'Robinsons Galleria Ortigas', 'February 26, 2025'],
        ['BACLIG, ALLAIN JUDE DAHILIG', 'Assistant Head Cook (OIC)', 'Robinsons Ilocos', 'September 26, 2025'],
        ['BAGUIO, DAYLE DARIUS CASTRO', 'Shift Manager (OIC)', 'Robinsons Ilocos', 'November 11, 2025'],
        ['ESTANTE, RODGENNE MIRAR', 'Assistant Head Cook (OIC)', 'Robinsons Iloilo', 'February 26, 2026'],
        ['ELORIAGA, JESICA ELGARAN', 'Restaurant Manager 1 (OIC)', 'Robinsons Iloilo', 'August 11, 2018'],
        ['MERINDO, KRISTIAN ALMANDRES', 'Assistant Head Cook (OIC)', 'Robinsons Imus', 'March 01, 2018'],
        ['GALINO, LEONIEL BALAWANG', 'Restaurant Manager 1 (OIC)', 'Robinsons Imus', 'September 01, 2017'],
        ['TUPAZ, RUEL AMMAY', 'Head Cook (OIC)', 'Robinsons Magnolia', 'June 15, 2016'],
        ['NACAITUNA, REY DANIEL PAGADUAN', 'Shift Manager (OIC)', 'Robinsons Magnolia', 'February 26, 2026'],
        ['DELA CRUZ, ARPIN CASTRO', 'Assistant Restaurant Manager (OIC)', 'Robinsons Malolos', 'April 01, 2025'],
        ['VILLANUEVA, JONILO HEREZA', 'Head Cook (OIC)', 'Robinsons Malolos', 'August 06, 2022'],
        ['MANGUBAT, NELIE JOY ESCUADRA', 'Assistant Restaurant Manager (OIC)', 'Robinsons Pagadian', 'August 10, 2023'],
        ['ALDAYA, JOSEPH ROY BUAG', 'Head Cook (OIC)', 'Robinsons Pagadian', 'September 15, 2013'],
        ['BACOSMO JR., ROMEO MINGUA', 'Head Cook (OIC)', 'Robinsons San Pedro', 'April 01, 2015'],
        ['JUGUETA, ARNTRUMAN CALUBAYAN', 'Restaurant Manager 1 (OIC)', 'Robinsons San Pedro', 'March 06, 2022'],
        ['MAGAT, ZAIRA JESSY ISIP', 'Assistant Restaurant Manager (OIC)', 'Robinsons Starmills', 'April 01, 2026'],
        ['DUCUT, ARDY SANTOS', 'Head Cook (OIC)', 'Robinsons Starmills', 'March 01, 2017'],
        ['AZUSANO, CARLMAND BARRENTO', 'Assistant Head Cook (OIC)', 'SM Annex', 'January 26, 2026'],
        ['SILVESTRE, DANNIA JESSA MARCOS', 'Assistant Restaurant Manager (OIC)', 'SM Annex', 'April 26, 2024'],
        ['OLIQUINO, JOHN RAY CANALE', 'Assistant Head Cook (OIC)', 'SM Bacoor', 'February 01, 2026'],
        ['MICAEL, PAOLA MARIE LEVISTE', 'Restaurant Manager 2 (OIC)', 'SM Bacoor', 'December 01, 2021'],
        ['ROSARIO, RODOLFO CALAUNAN', 'Assistant Head Cook (OIC)', 'SM Baguio', 'August 01, 2024'],
        ['CAOILE, MARIA LOVELY MENDOZA', 'Assistant Restaurant Manager (OIC)', 'SM Baguio', 'September 11, 2024'],
        ['FAJARDO, JEFFREY MORILLO', 'Head Cook (OIC)', 'SM Baliwag', 'September 26, 2022'],
        ['SALVADORA, SHEILA MARIE LEDESMA', 'Restaurant Manager 1 (OIC)', 'SM Baliwag', 'November 01, 2016'],
        ['CANDAVA, RONNEL DATINGALING', 'Assistant Head Cook (OIC)', 'SM Batangas', 'September 01, 2025'],
        ['CORRE, TONYBOY DELA VERGO', 'Assistant Restaurant Manager (OIC)', 'SM Batangas', 'October 05, 2023'],
        ['GANDO, JOHN ARVIN OCAMPO', 'Assistant Head Cook (OIC)', 'SM Bf Parañaque', 'January 26, 2026'],
        ['NARISENA, LEO LIGAN', 'Assistant Head Cook (OIC)', 'SM Bicutan', 'August 11, 2025'],
        ['TORIO, REX ESPORA', 'Head Cook (OIC)', 'SM Bicutan', 'January 01, 2021'],
        ['DELA CRUZ, MARY ANNE BAUTISTA', 'Restaurant Manager 1 (OIC)', 'SM Bicutan', 'June 14, 2017'],
        ['SAPLAD, EDWIN CAINGLES', 'Assistant Head Cook (OIC)', 'SM Calamba', 'June 11, 2024'],
        ['BARROT, EDMAR BINALINGBING', 'Assistant Restaurant Manager (OIC)', 'SM Calamba', 'December 01, 2024'],
        ['BARTOLATA, RONALDO CONCINCINO', 'Assistant Head Cook (OIC)', 'SM Caloocan', 'June 01, 2024'],
        ['ABONAL, JOSEPH DE GUZMAN', 'Restaurant Manager 1 (OIC)', 'SM Caloocan', 'August 26, 2023'],
        ['MAYO JR., JOHN RECORTE', 'Head Cook (OIC)', 'SM Cdo 2', 'May 15, 2019'],
        ['POLLEY, KHEVIN YAGOTYOT', 'Shift Manager (OIC)', 'SM Cdo 2', 'November 26, 2025'],
        ['DAVID, RON RYAN PASTRANA', 'Assistant Head Cook (OIC)', 'SM Cebu', 'October 01, 2018'],
        ['ATILANO, FRANKY GREGORIO', 'Assistant Restaurant Manager (OIC)', 'SM Cebu', 'August 01, 2016'],
        ['MARANAN, JESS JEFFERSON VICTORIA', 'Assistant Head Cook (OIC)', 'SM Center San Pedro', 'November 11, 2023'],
        ['CERVANTES, KOBE YBAÑEZ', 'Assistant Restaurant Manager (OIC)', 'SM Center San Pedro', 'July 01, 2025'],
        ['SANTOS, RICHARD BASILIO', 'Assistant Head Cook (OIC)', 'SM City Bataan', 'January 26, 2024'],
        ['PEREY, ANNA MELISSA LUNA', 'Assistant Restaurant Manager (OIC)', 'SM City Bataan', 'June 11, 2024'],
        ['CALIXTRO JR., SENANDO ROMERO', 'Assistant Head Cook (OIC)', 'SM City Sto. Tomas', 'March 26, 2026'],
        ['BISCOCHO, MARICRIS ALIVIO', 'Assistant Restaurant Manager (OIC)', 'SM City Sto. Tomas', 'May 01, 2024'],
        ['CONSTANTINO, JUSTIN MENDIORO', 'Assistant Restaurant Manager (OIC)', 'SM Clark', 'October 11, 2025'],
        ['TERGA, RAY MARK MAIDO', 'Head Cook (OIC)', 'SM Clark', 'April 30, 2018'],
        ['VILLASORDA, BERNARD SIAROT', 'Assistant Head Cook (OIC)', 'SM Consolacion', 'January 26, 2024'],
        ['CAJES, MELVIN YUCOT', 'Assistant Restaurant Manager (OIC)', 'SM Consolacion', 'August 01, 2025'],
        ['ANGELES, JOARO REY BUENAOBRA', 'Assistant Head Cook (OIC)', 'SM Daet', 'July 11, 2024'],
        ['JAPIZ, JOMAR MOLINA', 'Assistant Restaurant Manager (OIC)', 'SM Daet', 'June 02, 2024'],
        ['SOYOSA, JOSEPH BARRANTES', 'Head Cook (OIC)', 'SM Dasmariñas', 'June 15, 2022'],
        ['NODALO, DARYLL PINGKIAN', 'Restaurant Manager 2 (OIC)', 'SM Dasmariñas', 'December 01, 2014'],
        ['CABRERA, NELSON DALISAY', 'Assistant Head Cook (OIC)', 'SM Davao', 'November 11, 2023'],
        ['LIBO-ON, MITCHE GRACE ERAMEL', 'Restaurant Manager 1 (OIC)', 'SM Davao', 'October 26, 2019'],
        ['CEBRERO, ROLLEEN VALDOMAR', 'Head Cook (OIC)', 'SM Fairview', 'August 26, 2022'],
        ['VITALICIA, CRYSTALYN ESGUERRA', 'Restaurant Manager 2 (OIC)', 'SM Fairview', 'November 01, 2014'],
        ['DELO, RICHARD GAVINA', 'Head Cook (OIC)', 'SM Grace Residences', 'May 01, 2015'],
        ['QUEBRADO, ANTHONY QUINTANAR', 'Restaurant Manager 1 (OIC)', 'SM Grace Residences', 'September 26, 2017'],
        ['MICAEL, LIONEL MENDOZA', 'Assistant Head Cook (OIC)', 'SM Hypermarket Sucat', 'November 18, 2023'],
        ['PINALBA, REYMART ESPENILLA', 'Assistant Head Cook (OIC)', 'SM Iloilo', 'December 11, 2023'],
        ['LOJA, JICY PEREZ', 'Restaurant Manager 2 (OIC)', 'SM Iloilo', 'February 24, 2018'],
        ['ILANGA, HENNIE JILL KATE BALGOS', 'Shift Manager (OIC)', 'SM Iloilo Terminal', 'March 10, 2026'],
        ['DANTAYANA, MARY ANN ALMAN', 'Assistant Restaurant Manager (OIC)', 'SM Imus', 'February 26, 2024'],
        ['PANES, VIRGIL FLORES', 'Head Cook (OIC)', 'SM Imus', 'November 01, 2018'],
        ['BEBERINO, MARK RANDEL ROSELLO', 'Shift Manager (OIC)', 'SM Imus', 'December 11, 2024'],
        ['CAÑIZARES, REY TICUAS', 'Shift Manager (OIC)', 'SM Lanang', 'February 19, 2026'],
        ['FERNANDEZ, HARRIS BEN', 'Assistant Head Cook (OIC)', 'SM Laoag', 'October 11, 2025'],
        ['ALFEREZ JR., DANIEL DE LA CRUZ', 'Assistant Restaurant Manager (OIC)', 'SM Laoag', 'November 16, 2024'],
        ['REANZARES, RINO LAVAYNA', 'Head Cook (OIC)', 'SM Las Piñas', 'September 01, 2017'],
        ['REGALARIO, VINCE ERIC BAYABAN', 'Shift Manager (OIC)', 'SM Las Piñas', 'July 07, 2025'],
        ['PINPIN, MARK CASAS', 'Head Cook (OIC)', 'SM Legazpi', 'June 01, 2023'],
        ['BILANSING, IVY PROTOMARTIR', 'Restaurant Manager 1 (OIC)', 'SM Legazpi', 'February 26, 2020'],
        ['AGUILA, RENSON BRIAN ROBLES', 'Assistant Head Cook (OIC)', 'SM Lipa', 'February 15, 2023'],
        ['SILFAVAN, JOMAR DACULA', 'Head Cook (OIC)', 'SM Lucena', 'November 15, 2018'],
        ['BAES, KERROLL JOHN TAN', 'Restaurant Manager 1 (OIC)', 'SM Lucena', 'September 26, 2023'],
        ['PARALLAG, JAMES KEVIN GODINO', 'Assistant Head Cook (OIC)', 'SM Mall Of Asia', 'February 11, 2024'],
        ['DACANAY, CRISANTO ESTRADA', 'Head Cook (OIC)', 'SM Mall Of Asia', 'May 01, 2019'],
        ['BERNAS, GIL CLINTON ALTIVEROS', 'Restaurant Manager 2 (OIC)', 'SM Mall Of Asia', 'December 11, 2021'],
        ['ROSAL, RAUL CORPIN', 'Head Cook (OIC)', 'SM Manila', 'June 01, 2014'],
        ['LANCERO, EDELYN GALLEGO', 'Restaurant Manager 1 (OIC)', 'SM Manila', 'March 26, 2022'],
        ['RESURRECCION, JAYCEE PAGDANGANAN', 'Head Cook (OIC)', 'SM Marilao', 'February 20, 2020'],
        ['MARTINEZ, RODEL CLAVILLAS', 'Restaurant Manager 1 (OIC)', 'SM Marilao', ''],
        ['TORRES, JOEL MARAYA', 'Assistant Head Cook (OIC)', 'SM Masinag', 'April 11, 2025'],
        ['DE VERA, AILEEN ROSE AGUILAR', 'Assistant Restaurant Manager (OIC)', 'SM Masinag', 'October 01, 2024'],
        ['BOBAN, MICHAEL DUERO', 'Head Cook (OIC)', 'SM Megamall 2', 'December 26, 2021'],
        ['RABI, KRISTINE MAY GAL', 'Restaurant Manager 2 (OIC)', 'SM Megamall 2', 'June 01, 2019'],
        ['VILLANUEVA, KIM ALBERT FRONDEZ', 'Shift Manager (OIC)', 'SM Naga', 'July 01, 2025'],
        ['DAPUNAN JR., ROGELIO CABAING', 'Head Cook (OIC)', 'SM North Edsa', 'October 01, 2018'],
        ['SARMIENTO, BRIEANNA CIANEN CALUMPAG', 'Shift Manager (OIC)', 'SM North Edsa', 'September 11, 2025'],
        ['DIOCALES, AILAINE NICOLE START', 'Assistant Restaurant Manager (OIC)', 'SM Novaliches', 'June 15, 2021'],
        ['DELA RITA, ROMMEL GUEVARA', 'Head Cook (OIC)', 'SM Novaliches', 'September 01, 2018'],
        ['ARELLANO, MICHAEL ESPIRITU', 'Head Cook (OIC)', 'SM Olongapo 2', 'March 01, 2017'],
        ['SANCHEZ, JANE AIRA ESTILON', 'Shift Manager (OIC)', 'SM Olongapo 2', 'February 01, 2026'],
        ['VILLANUEVA, ALVIN VERCELES', 'Assistant Head Cook (OIC)', 'SM One Ecom', 'July 15, 2023'],
        ['PANOGAN, JEZZA MARIE MORALES', 'Assistant Restaurant Manager (OIC)', 'SM One Ecom', 'April 11, 2025'],
        ['GONZALES, ALEXANDER PADER', 'Assistant Head Cook (OIC)', 'SM Pampanga', 'April 01, 2025'],
        ['HERNANDEZ, JENNILYN MALLARI', 'Restaurant Manager 2 (OIC)', 'SM Pampanga', 'November 01, 2019'],
        ['SIMON, JAYSON RAMOS', 'Head Cook (OIC)', 'SM Pasig', 'February 11, 2020'],
        ['MAGBANUA, GILVEN DESAMPARO', 'Assistant Head Cook (OIC)', 'SM Puerto Princesa', 'May 01, 2024'],
        ['PASCO, ROSE ANN DELA TORRE', 'Assistant Restaurant Manager (OIC)', 'SM Puerto Princesa', 'March 26, 2024'],
        ['MENDOZA, CHRISTIAN NABOR', 'Assistant Head Cook (OIC)', 'SM Rosales', 'May 18, 2023'],
        ['QUIMING, CHRISTIAN ROY DE GUERTO', 'Assistant Restaurant Manager (OIC)', 'SM Rosales', 'May 01, 2026'],
        ['RUBIO, CHRISTIAN LEIDO', 'Assistant Head Cook (OIC)', 'SM Rosario', 'June 11, 2023'],
        ['OLIVER, ADAN ORTIZ', 'Assistant Restaurant Manager (OIC)', 'SM Rosario', 'February 01, 2026'],
        ['SALARZA, RAYMOND MAGBANUA', 'Assistant Head Cook (OIC)', 'SM San Lazaro', 'September 11, 2023'],
        ['OCHEA, JAIME SOJON', 'Head Cook (OIC)', 'SM San Lazaro', 'January 11, 2022'],
        ['MEDILO, JEA BELL DE PEDRO', 'Restaurant Manager 2 (OIC)', 'SM San Lazaro', 'November 11, 2021'],
        ['MATIBAG JR., DANILO PADAYAO', 'Assistant Head Cook (OIC)', 'SM San Pablo', 'September 11, 2024'],
        ['TAPAS, DENNIS MARASIGAN', 'Shift Manager (OIC)', 'SM San Pablo', 'September 11, 2025'],
        ['BARLISO, RIEZA BACUS', 'Assistant Restaurant Manager (OIC)', 'SM Seaside', 'April 26, 2021'],
        ['CABURNAY, WILBERTO BAROMAN', 'Head Cook (OIC)', 'SM Seaside', 'February 11, 2022'],
        ['ROCA, VICTORINO SARMIENTO', 'Assistant Head Cook (OIC)', 'SM Sorsogon', 'January 16, 2025'],
        ['PARAS, SHERYL EVASCO', 'Restaurant Manager 1 (OIC)', 'SM Sorsogon', 'March 26, 2025'],
        ['DELOS REYES, CATHERINE NICOLAS', 'Assistant Restaurant Manager (OIC)', 'SM Southmall', 'November 11, 2023'],
        ['CAMBE, RIA JOCELYN HUMARANG', 'Assistant Restaurant Manager (OIC)', 'SM Sta. Mesa', 'February 26, 2023'],
        ['MANAHAN, PAULO ROMAN DELARMENTE', 'Head Cook (OIC)', 'SM Sta. Mesa', 'June 01, 2016'],
        ['DALIGDIG, JOVEN ARCO', 'Head Cook (OIC)', 'SM Sta. Rosa', 'April 01, 2021'],
        ['LACANARIA, CRISTOBAL REYES', 'Head Cook (OIC)', 'SM Sucat 2', 'September 11, 2018'],
        ['JACOSALEM, RHEA CAMACHO', 'Restaurant Manager 2 (OIC)', 'SM Sucat 2', 'June 26, 2021'],
        ['REOMA, JOVEN ELARDO', 'Assistant Head Cook (OIC)', 'SM Tanza', 'March 11, 2026'],
        ['CERIO, PRECHELL AMORA', 'Restaurant Manager 2 (OIC)', 'SM Tanza', 'November 01, 2022'],
        ['PATUNGAN, BRIAN OCHUA', 'Assistant Head Cook (OIC)', 'SM Tarlac', 'March 01, 2025'],
        ['SEMBRANO, ABIGAIL MALLARI', 'Assistant Restaurant Manager (OIC)', 'SM Tarlac', 'May 01, 2025'],
        ['VILLARIN, DENNIS SOLIMAN', 'Assistant Head Cook (OIC)', 'SM Taytay', 'February 11, 2024'],
        ['TORRENUEVA, KIA DOGUILES', 'Assistant Restaurant Manager (OIC)', 'SM Taytay', 'October 11, 2025'],
        ['CALVES JR., FERNANDO CABSABAN', 'Assistant Head Cook (OIC)', 'SM Telabastagan', 'July 01, 2024'],
        ['SUMAGANG, MARC HAROLD DE GUZMAN', 'Shift Manager (OIC)', 'SM Telabastagan', 'April 06, 2025'],
        ['MERIALES, DARWIN SAMSON', 'Assistant Head Cook (OIC)', 'SM Urdaneta', 'January 26, 2025'],
        ['DASIG JR., ARNOLD ABARE', 'Restaurant Manager 1 (OIC)', 'SM Urdaneta', 'October 11, 2024'],
        ['NARITO, MARVIN BONGON', 'Assistant Head Cook (OIC)', 'SM Valenzuela', 'August 11, 2025'],
        ['ESTRELLON, BENITO JOHN FLORES', 'Assistant Restaurant Manager (OIC)', 'SM Valenzuela', 'March 26, 2026'],
        ['MAONGCA, RANDEL PAGDANGANAN', 'Assistant Head Cook (OIC)', 'SM Zamboanga', 'March 19, 2026'],
        ['DE LOS REYES JR., ERNESTO CAMPOSANO', 'Restaurant Manager 2', 'SM Zamboanga', 'February 19, 2018'],
        ['NERO JR., ARNEL ACERO', 'Assistant Head Cook (OIC)', 'Sta. Lucia Mall', 'March 01, 2024'],
        ['GAZA, GABRIEL QUILLOPE', 'Assistant Restaurant Manager (OIC)', 'Sta. Lucia Mall', ''],
        ['VENTURA, PAUL VINCENT FRANCISCO', 'Head Cook (OIC)', 'Unimart Capitol Commons', 'January 11, 2021'],
        ['MENDOZA, ROLAND RODRIGUEZ', 'Assistant Head Cook (OIC)', 'Walter Mart Sta. Maria', 'October 05, 2025'],
        ['GATUS, ROSANA SUMAYANG', 'Assistant Restaurant Manager (OIC)', 'Walter Mart Sta. Maria', 'May 21, 2025'],
    ];

    public function run(): void
    {
        $dryRun = filter_var(env('OLD_OIC_BACKFILL_DRY_RUN', false), FILTER_VALIDATE_BOOLEAN);
        $branches = Branch::all()->keyBy(fn (Branch $branch) => $this->normalize($branch->name));
        $employees = Employee::with('latest_position')
            ->get()
            ->groupBy(fn (Employee $employee) => $this->normalize($employee->full_name));

        $updated = [];
        $skipped = [];
        $review = [];

        $processRows = function () use ($dryRun, $branches, $employees, &$updated, &$skipped, &$review) {
            foreach ($this->rows as [$employeeName, $position, $branchName, $appointedDate]) {
                if (!$appointedDate) {
                    $skipped[] = "{$employeeName} / {$branchName}: blank appointed date";
                    continue;
                }

                $dateAppointed = Carbon::parse($appointedDate)->format('Y-m-d');
                $branch = $branches->get($this->normalize($branchName));
                if (!$branch) {
                    $review[] = "{$employeeName} / {$branchName}: branch not found";
                    continue;
                }

                $matches = $employees->get($this->normalize($employeeName), collect());
                if ($matches->count() !== 1) {
                    $review[] = "{$employeeName} / {$branchName}: employee match count {$matches->count()}";
                    continue;
                }

                /** @var Employee $employee */
                $employee = $matches->first();
                $assignedSlots = $this->assignedOicSlots($branch, $employee->employee_number);
                if (empty($assignedSlots)) {
                    $review[] = "{$employeeName} / {$branchName}: not currently assigned as branch/kitchen OIC";
                    continue;
                }

                $latestPosition = $employee->latest_position;
                if (!$latestPosition) {
                    $review[] = "{$employeeName} / {$branchName}: no latest employee_position row";
                    continue;
                }

                $oldDate = $latestPosition->date_appointed;
                if (!$dryRun) {
                    $latestPosition->update([
                        'oic_assignment' => 'Yes',
                        'date_appointed' => $dateAppointed,
                    ]);

                    if ($employee->oic_status !== 'OIC') {
                        $employee->update(['oic_status' => 'OIC']);
                    }
                }

                $updated[] = "{$employeeName} / {$branchName} [" . implode(', ', $assignedSlots) . "]: {$oldDate} -> {$dateAppointed}";
            }
        };

        $dryRun ? $processRows() : DB::transaction($processRows);

        $this->command?->info(($dryRun ? 'Dry run complete.' : 'Backfill complete.') . ' Updated: ' . count($updated));
        foreach ($updated as $line) {
            $this->command?->line("UPDATED: {$line}");
        }

        $this->command?->warn('Needs review / possible replacement: ' . count($review));
        foreach ($review as $line) {
            $this->command?->warn("REVIEW: {$line}");
        }

        $this->command?->info('Skipped blank dates: ' . count($skipped));
        foreach ($skipped as $line) {
            $this->command?->line("SKIPPED: {$line}");
        }
    }

    private function assignedOicSlots(Branch $branch, ?string $employeeNumber): array
    {
        if (!$employeeNumber) {
            return [];
        }

        $slots = [];
        if ((string) $branch->branch_head_employee_number === (string) $employeeNumber) {
            $slots[] = 'branch_head';
        }
        if ((string) $branch->kitchen_oic_employee_number === (string) $employeeNumber) {
            $slots[] = 'kitchen_oic';
        }
        if ((string) $branch->kitchen_oic_2_employee_number === (string) $employeeNumber) {
            $slots[] = 'kitchen_oic_2';
        }

        return $slots;
    }

    private function normalize(?string $value): string
    {
        $value = trim((string) $value);
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
        return trim(preg_replace('/\s+/', ' ', $value));
    }
}
