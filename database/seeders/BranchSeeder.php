<?php

namespace Database\Seeders;

use App\Models\Branch;
use DateTime;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run() : void
    {
        $branches = [
            [
             "Branch ID"=> "SFI00007",
             "Branch Name"=> "IT DEPARTMENT",
             "Short Name"=> "ITD",
             "Mall Admin"=> "NONE",
             "Address"=> "NO. 84 STO. DOMINGO ST., COR. N.S. AMORANTO, QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "5-10-2007",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00008",
             "Branch Name"=> "LOGISTICS DIVISION",
             "Short Name"=> "LOG",
             "Mall Admin"=> "NONE",
             "Address"=> "NO. 84 STO. DOMINGO ST., COR. N.S. AMORANTO, QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "5-10-2007",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00009",
             "Branch Name"=> "MARKETING DEPARTMENT",
             "Short Name"=> "MKD",
             "Mall Admin"=> "NONE",
             "Address"=> "NO. 15 CATMON ST, TALAYAN VILLAGE QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "5-10-2007",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00011",
             "Branch Name"=> "PROJECT MANAGEMENT DEPARTMENT",
             "Short Name"=> "PMD",
             "Mall Admin"=> "NONE",
             "Address"=> "NO. 84 STO. DOMINGO ST., COR. N.S. AMORANTO, QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "5-10-2007",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00012",
             "Branch Name"=> "PURCHASING DEPARTMENT",
             "Short Name"=> "PRD",
             "Mall Admin"=> "NONE",
             "Address"=> "NO. 70 SAN ANTONIO ST., SFDM, QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "5-10-2007",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00013",
             "Branch Name"=> "SAN ANTONIO WAREHOUSE",
             "Short Name"=> "SAW",
             "Mall Admin"=> "NONE",
             "Address"=> "NO. 70 SAN ANTONIO ST., SFDM, QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "5-10-2007",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00017",
             "Branch Name"=> "ABREEZA DAVAO",
             "Short Name"=> "ABZ",
             "Mall Admin"=> "AYALA MALL",
             "Address"=> "LEVEL 3 FOOD CHOICES SPACE 3016C ABREEZA MALL, JP LAUREL AVENUE DAVAO CITY",
             "Location"=> "REGION XI",
             "Date Open"=> "5-12-2011",
             "Status"=> "inactive"
            ],
            [
             "Branch ID"=> "SFI00018",
             "Branch Name"=> "AYALA ATC",
             "Short Name"=> "ATC",
             "Mall Admin"=> "AYALA MALL",
             "Address"=> "STALL 8 FOOD CHOICES AT THE TOWN 2ND FLRR ATC MUNTINLUPA CITY",
             "Location"=> "NCR",
             "Date Open"=> "2-1-2010",
             "Status"=> "inactive"
            ],
            [
             "Branch ID"=> "SFI00020",
             "Branch Name"=> "AYALA GLORIETTA 4",
             "Short Name"=> "G4",
             "Mall Admin"=> "AYALA MALL",
             "Address"=> "SPACE #6 FOOD CHOICES AT GLORIETTA, GLORIETTA 4 AYALA CTR MAKATI CITY",
             "Location"=> "NCR",
             "Date Open"=> "8-30-2008",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00033",
             "Branch Name"=> "ETON CYBERPAD - ORTIGAS",
             "Short Name"=> "ECO",
             "Mall Admin"=> "OTHER",
             "Address"=> "UG66 UPPER GROUND FLOOR E-LITE @ ETON CYBERPOD CORINTHIAN EDSA COR ORTIGAS AVE UGONG NORTH QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "1-2-2010",
             "Status"=> "inactive"
            ],
            [
             "Branch ID"=> "SFI00039",
             "Branch Name"=> "ROBINSONS CEBU",
             "Short Name"=> "RCE",
             "Mall Admin"=> "ROBINSONS MALL",
             "Address"=> "ROBINSON CYBERGATE CEBU LEVEL 1 123-126 DON GIL GARCIA ST. CAPITOL SITE CEBU CITY CEBU",
             "Location"=> "REGION VII",
             "Date Open"=> "4-15-2010",
             "Status"=> "inactive"
            ],
            [
             "Branch ID"=> "SFI00052",
             "Branch Name"=> "SM ADRIATICO",
             "Short Name"=> "ADT",
             "Mall Admin"=> "SM MALL",
             "Address"=> "G-F SM HYPERMARKET M. ADRIATICO ST. MALATE MANILA",
             "Location"=> "NCR",
             "Date Open"=> "2-1-2010",
             "Status"=> "inactive"
            ],
            [
             "Branch ID"=> "SFI00016",
             "Branch Name"=> "ALI MALL",
             "Short Name"=> "CUB",
             "Mall Admin"=> "OTHER",
             "Address"=> "SPACE 00052-00052S G-F ALI MALL 1 ARANETA CENTER QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "07-31-2008",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00021",
             "Branch Name"=> "AYALA HARBOR POINT SUBIC",
             "Short Name"=> "HPS",
             "Mall Admin"=> "AYALA MALL",
             "Address"=> "SPACE NO.232 AND 233 HARBOR POINT SUBIC BAY FREEPORT ZONE",
             "Location"=> "REGION III",
             "Date Open"=> "08-03-2012",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00024",
             "Branch Name"=> "AYALA PAVILION MALL",
             "Short Name"=> "PAV",
             "Mall Admin"=> "AYALA MALL",
             "Address"=> "SPACE 152-A G-F BLDG. B. PAVILLION MALL SAN ANTONIO BINAN LAGUNA",
             "Location"=> "REGION IVA",
             "Date Open"=> "05-21-2010",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00026",
             "Branch Name"=> "AYALA SOUTH PARK",
             "Short Name"=> "SOP",
             "Mall Admin"=> "AYALA MALL",
             "Address"=> "G-F UNIT G207 & G209 SOUTH PARK, ALABANG, CITY OF MUNITNLUPA",
             "Location"=> "NCR",
             "Date Open"=> "11-29-2016",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00027",
             "Branch Name"=> "AYALA THE DISTRICT IMUS",
             "Short Name"=> "IMU",
             "Mall Admin"=> "AYALA MALL",
             "Address"=> "GROUND FLOOR SPACE 169-170 THE DISTRICT-CAVITE, CORNERS DAANG HARI RD AND AGUINALDO HIGHWAY, IMUS CAVITE",
             "Location"=> "REGION IVA",
             "Date Open"=> "12-14-2012",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00029",
             "Branch Name"=> "AYALA TRINOMA MALL",
             "Short Name"=> "TNM",
             "Mall Admin"=> "AYALA MALL",
             "Address"=> "LEVEL 1 SPACE 1096 TRINOMA MALL EDSA CORNER NORTH AVENUE QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "10-18-2011",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00031",
             "Branch Name"=> "CEDAR PEAK BAGUIO",
             "Short Name"=> "CED",
             "Mall Admin"=> "OTHER",
             "Address"=> "UNIT PF-10 CEDAR PEAL GEN. LUNA COR. MABINI ST. BRGY. KABAYANIHAN BAGUIO CITY.",
             "Location"=> "CAR",
             "Date Open"=> "03-05-2016",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00032",
             "Branch Name"=> "CITY PLACE SQUARE",
             "Short Name"=> "CPS",
             "Mall Admin"=> "OTHER",
             "Address"=> "TRANSFERRED TO=> 2ND FLOOR - UNIT A1, LUCKY CHINATOWN  ANNEX B, CALLE FELIPE CORNER LA CHAMBRE, BARANGAY 293, ZONE 28",
             "Location"=> "NCR",
             "Date Open"=> "06-30-2011",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00034",
             "Branch Name"=> "FESTIVAL MALL",
             "Short Name"=> "FES",
             "Mall Admin"=> "OTHER",
             "Address"=> "SPACE RL-LW LEVEL 1 FESTIVAL SUPERMALL FILINVEST CORP. CITY ALABANG MUNTINLUPA",
             "Location"=> "NCR",
             "Date Open"=> "09-25-2009",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00035",
             "Branch Name"=> "FISHER MALL",
             "Short Name"=> "FSH",
             "Mall Admin"=> "OTHER",
             "Address"=> "2ND FLR UNIT L5-L6, FISHER MALL, CORNER QUEZON AVE. AND ROOSEVELT AVE., BRGY. STA. CRUZ., Q.C",
             "Location"=> "NCR",
             "Date Open"=> "01-15-2013",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00037",
             "Branch Name"=> "LANDMARK MAKATI",
             "Short Name"=> "LAN",
             "Mall Admin"=> "AYALA MALL",
             "Address"=> "STALL 20 THE LANDMARK FOOD CENTER MAKATI AVE. CENTER, MAKATI CITY",
             "Location"=> "NCR",
             "Date Open"=> "12-15-2008",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00038",
             "Branch Name"=> "MCKINLEY HILLS",
             "Short Name"=> "MKY",
             "Mall Admin"=> "OTHER",
             "Address"=> "GROUND FLOOR UNIT R109-R110 VENICE PIAZZA MCKINLEY HILL TAGUIG",
             "Location"=> "NCR",
             "Date Open"=> "03-16-2016",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00040",
             "Branch Name"=> "ROBINSONS ERMITA",
             "Short Name"=> "ERM",
             "Mall Admin"=> "ROBINSONS MALL",
             "Address"=> "LEVEL 1 SPACE 160-163 ROBINSONS PLACE MLA-MIDTOWN M. ADRIATICO ST. ERMITA, MANILA",
             "Location"=> "NCR",
             "Date Open"=> "10-25-2007",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00110",
             "Branch Name"=> "SM LEMERY",
             "Short Name"=> "LEM",
             "Mall Admin"=> "SM MALL",
             "Address"=> "G-F UNIT 136 SM CENTER LEMERY DISTRICT IV (POB.) LEMERY BATANGAS PHILIPPINES 4209",
             "Location"=> "REGION IVA",
             "Date Open"=> "12-15-2017",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00041",
             "Branch Name"=> "ROBINSONS FORUM",
             "Short Name"=> "RPI",
             "Mall Admin"=> "ROBINSONS MALL",
             "Address"=> "UNIT 21006 & 21007 LEVEL 2 FORUM ROBINSONS 30 EDSA CORNER PIONEER ST. BARANGKA ILAYA MANDALUYONG CITY",
             "Location"=> "NCR",
             "Date Open"=> "01-28-2011",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00109",
             "Branch Name"=> "SM PULILAN",
             "Short Name"=> "PUL",
             "Mall Admin"=> "SM MALL",
             "Address"=> "G-F UNIT 108 SM CENTER PULILAN SANTO CRISTO PULILAN BULACAN PHILIPPINES 3005",
             "Location"=> "REGION III",
             "Date Open"=> "12-12-2017",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00108",
             "Branch Name"=> "SM BREEZE RESIDENCES",
             "Short Name"=> "BRZ",
             "Mall Admin"=> "SM MALL",
             "Address"=> "G-F UNIT 103-104 SMDC BREEZE RESIDENCES BGRY 142, PASAY CITY NCR, FOURTH DISTRICT PHILIPPINES 1300",
             "Location"=> "NCR",
             "Date Open"=> "02-24-2018",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00048",
             "Branch Name"=> "ROBINSONS MALOLOS",
             "Short Name"=> "MLL",
             "Mall Admin"=> "ROBINSONS MALL",
             "Address"=> "UNIT 1-01147, ROBINSONS PLACE MALOLOS, MCARTHUR HIGHWAY, BRGY. MABOLO MALOLOS, BULACAN",
             "Location"=> "REGION III",
             "Date Open"=> "12-12-2013",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00049",
             "Branch Name"=> "ROBINSONS METRO EAST",
             "Short Name"=> "MET",
             "Mall Admin"=> "ROBINSONS MALL",
             "Address"=> "L2 154-158 LEVEL 2 ROBINSONS METRO EAST DELA PAZ PASIG CITY",
             "Location"=> "NCR",
             "Date Open"=> "11-25-2009",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00051",
             "Branch Name"=> "ROBINSONS TAGAYTAY",
             "Short Name"=> "TGY",
             "Mall Admin"=> "ROBINSONS MALL",
             "Address"=> "LEVEL 1-104 SUMMIT RIDGE PROMENADE, NATIONAL ROAD, BRGY MAHARLIKA, TAGAYTAY CITY",
             "Location"=> "NCR",
             "Date Open"=> "02-22-2013",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00053",
             "Branch Name"=> "SM ANNEX",
             "Short Name"=> "ANX",
             "Mall Admin"=> "SM MALL",
             "Address"=> "2-F AX1- 223-224 SM CITY ANNEX EDSA COR NORTH AVENUE BRGY STO. CRISTO QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "05-13-2011",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00054",
             "Branch Name"=> "SM BACOLOD",
             "Short Name"=> "BCL",
             "Mall Admin"=> "SM MALL",
             "Address"=> "G-F UNIT B 115A-B 116A SM CITY BACOLOD CITY BISHOP ANTONIO Y. FORTICH                 ",
             "Location"=> "REGION VI",
             "Date Open"=> "02-26-2016",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00055",
             "Branch Name"=> "SM BACOOR",
             "Short Name"=> "BCR",
             "Mall Admin"=> "SM MALL",
             "Address"=> "UGF 1044,, SM CITY BACOOR PEDESTRIAN OVERPASS, BACOOR, 4102 CAVITE",
             "Location"=> "REGION IVA",
             "Date Open"=> "04-01-2011",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00057",
             "Branch Name"=> "SM BALIWAG",
             "Short Name"=> "BWG",
             "Mall Admin"=> "SM MALL",
             "Address"=> "SPACE 1102-1103 SM CITY, BRGY. PAGALA,, DOÑA REMEDIOS TRINIDAD HWY, BALIUAG, BULACAN",
             "Location"=> "REGION III",
             "Date Open"=> "10-31-2012",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00058",
             "Branch Name"=> "SM BATANGAS",
             "Short Name"=> "BTG",
             "Mall Admin"=> "SM MALL",
             "Address"=> "G-F R183N-R183O SM CITY BATANGAS, PALLOCAN KANLURAN, BATANGAS CITY",
             "Location"=> "REGION IVA",
             "Date Open"=> "12-03-2016",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00059",
             "Branch Name"=> "SM BICUTAN",
             "Short Name"=> "BIC",
             "Mall Admin"=> "SM MALL",
             "Address"=> "SPACE 124-125(B) UGF BLDG. SM CITY BICUTAN DOÑA SOLEDAD AVE. COR WSR BRGY. DON BOSCO, PARAÑAQUE",
             "Location"=> "NCR",
             "Date Open"=> "06-08-2010",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00060",
             "Branch Name"=> "SM CALAMBA",
             "Short Name"=> "CAL",
             "Mall Admin"=> "SM MALL",
             "Address"=> "GF 110 SM CITY CALAMBA NATIONAL HIGHWAY BRGY REAL CALAMBA CITY LAGUNA",
             "Location"=> "REGION IVA",
             "Date Open"=> "10-15-2010",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00062",
             "Branch Name"=> "SM CEBU",
             "Short Name"=> "SCE",
             "Mall Admin"=> "SM MALL",
             "Address"=> "SPACE 2107, 2F SM CITY CEBU, NORTH RECLAMATION AREA, CEBU CITY",
             "Location"=> "REGION VII",
             "Date Open"=> "04-15-2010",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00067",
             "Branch Name"=> "SM FAIRVIEW",
             "Short Name"=> "FVW",
             "Mall Admin"=> "SM MALL",
             "Address"=> "SPACE 191, MAIN BLDG. UPPER GROUND FLOOR SM CITY FAIRVIEW, QUIRINO HIGHWAY, FAIRVIEW QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "09-14-2012",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00068",
             "Branch Name"=> "SM GENSAN",
             "Short Name"=> "GEN",
             "Mall Admin"=> "SM MALL",
             "Address"=> "SPACE 185-186 SM CITY GENERAL SANTOS CORNER SANTIAGO  BOULEVARD AND SAN MIGUEL STREET, GENERAL SANTOS CITY",
             "Location"=> "REGION XII",
             "Date Open"=> "08-09-2012",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00071",
             "Branch Name"=> "SM LAS PIÑAS",
             "Short Name"=> "LAS",
             "Mall Admin"=> "SM MALL",
             "Address"=> "UGF 202&203 A. SM CENTER LAS PINAS ALABANG ZAPOTE RD. PAMPLONA DOS LAS PINAS CITY",
             "Location"=> "NCR",
             "Date Open"=> "10-02-2009",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00074",
             "Branch Name"=> "SM MALL OF ASIA",
             "Short Name"=> "MOA",
             "Mall Admin"=> "SM MALL",
             "Address"=> "G-F 135 (EM) SM MALL OF ASIA CENTRAL BUSINESS PARK, PASAY CITY",
             "Location"=> "NCR",
             "Date Open"=> "08-01-2007",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00075",
             "Branch Name"=> "SM MANILA",
             "Short Name"=> "MAN",
             "Mall Admin"=> "SM MALL",
             "Address"=> "UGF 143-144 SM CITY MANILA CONCEPTION ST. COR ARROCEROS ST. SAN MARCELINO ST. ERMITA MANILA",
             "Location"=> "NCR",
             "Date Open"=> "01-29-2010",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00076",
             "Branch Name"=> "SM MARILAO",
             "Short Name"=> "MRL",
             "Mall Admin"=> "SM MALL",
             "Address"=> "G-F UNIT 115 & 175 SM CITY MARILAO MC ARTHUR HIGHWAY, BRGY IBAYO MARILAO BULACAN",
             "Location"=> "REGION III",
             "Date Open"=> "11-15-2010",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00077",
             "Branch Name"=> "SM MASINAG",
             "Short Name"=> "MSG",
             "Mall Admin"=> "SM MALL",
             "Address"=> "UGF SPACE 132 SM CITY MASINAG BRGY. MAYAMOT ANTIPOLO CITY",
             "Location"=> "REGION IVA",
             "Date Open"=> "05-06-2011",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00079",
             "Branch Name"=> "SM NAGA",
             "Short Name"=> "NAG",
             "Mall Admin"=> "SM MALL",
             "Address"=> "UNIT 110, SM CITY NAGA, CBD II, BARANGAY TRIANGULO, NAGA CITY",
             "Location"=> "REGION V",
             "Date Open"=> "08-31-2012",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00080",
             "Branch Name"=> "SM NORTH EDSA",
             "Short Name"=> "NED",
             "Mall Admin"=> "SM MALL",
             "Address"=> "CC 105-B UPPER GROUND FLOOR SM CITY NORTH AVE COR EDSA QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "01-22-2008",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00081",
             "Branch Name"=> "SM NOVALICHES",
             "Short Name"=> "NOV",
             "Mall Admin"=> "SM MALL",
             "Address"=> "G-F UNIT 141 SM CITY NOVALICHES QUIRINO HIGHWAY SAN BARTOLOME NOVALICHES QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "10-22-2010",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00082",
             "Branch Name"=> "SM OLONGAPO",
             "Short Name"=> "OLO",
             "Mall Admin"=> "SM MALL",
             "Address"=> "STALL # 203 SM CITY OLONGAPO MAGSAYSAY DRIVE COR. GORDON AVENUE, PAG-ASA OLONGAPO CITY",
             "Location"=> "REGION III",
             "Date Open"=> "02-10-2012",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00083",
             "Branch Name"=> "SM ONE ECOM",
             "Short Name"=> "1EC",
             "Mall Admin"=> "SM MALL",
             "Address"=> "G LEVEL UNIT E13-E14 ONE E-COM CENTER MOA COMPLEX PASAY CITY",
             "Location"=> "NCR",
             "Date Open"=> "11-07-2008",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00084",
             "Branch Name"=> "SM PAMPANGA",
             "Short Name"=> "PAM",
             "Mall Admin"=> "SM MALL",
             "Address"=> "SM PP105, SM CITY PAMPANGA, SAN JOSE SAN FERNANDO PAMPANGA",
             "Location"=> "REGION III",
             "Date Open"=> "07-04-2008",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00085",
             "Branch Name"=> "SM PASIG",
             "Short Name"=> "PAS",
             "Mall Admin"=> "SM MALL",
             "Address"=> "G-F SPACE 121 SM SUPERCENTER PASIG CITY",
             "Location"=> "NCR",
             "Date Open"=> "09-03-2007",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00086",
             "Branch Name"=> "SM ROSALES",
             "Short Name"=> "RSL",
             "Mall Admin"=> "SM MALL",
             "Address"=> "SPACE 193-194A SM CITY ROSALES MCARTHUR HIGHWAY CARMEN EAST ROSALES PANGASINAN 2441",
             "Location"=> "REGION I",
             "Date Open"=> "09-16-2010",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00087",
             "Branch Name"=> "SM ROSARIO",
             "Short Name"=> "RSR",
             "Mall Admin"=> "SM MALL",
             "Address"=> "GF SMRO 109 SM CITY ROSARIO BRGY TEJEROS CONVENTION ROSARIO CAVITE",
             "Location"=> "REGION IVA",
             "Date Open"=> "11-20-2009",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00103",
             "Branch Name"=> "VISTA MALL BATAAN",
             "Short Name"=> "VBA",
             "Mall Admin"=> "OTHER",
             "Address"=> "GF 058 VISTA MALL, ROMAN HIGHWAY CUPANG PROPER, CITY OF BALANGA ( CAPITAL)    BATAAN",
             "Location"=> "REGION III",
             "Date Open"=> "10-20-2016",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00089",
             "Branch Name"=> "SM SAN LAZARO",
             "Short Name"=> "SAN",
             "Mall Admin"=> "SM MALL",
             "Address"=> "UGF SPACE 115 SM CITY SAN LAZARO F. HUERTAS COR AH LACSON ST. STA. CRUZ MANILA",
             "Location"=> "NCR",
             "Date Open"=> "05-30-2008",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00090",
             "Branch Name"=> "SM SAN PABLO",
             "Short Name"=> "SPA",
             "Mall Admin"=> "SM MALL",
             "Address"=> "GF 150 SM CITY SAN PABLO NATIONAL HIGHWAY BRGY SAN RAFAEL SAN PABLO CITY 4000",
             "Location"=> "REGION IVA",
             "Date Open"=> "10-01-2010",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00093",
             "Branch Name"=> "SM STA. MESA",
             "Short Name"=> "STM",
             "Mall Admin"=> "SM MALL",
             "Address"=> "SMST 131B SM CITY STA. MESA R. MAGSAYSAY BLVD COR G. ARANETA DONA IMELDA QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "01-17-2009",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00102",
             "Branch Name"=> "UPTOWN MALL",
             "Short Name"=> "UPT",
             "Mall Admin"=> "OTHER",
             "Address"=> "4TH FLOOR UNIT D26 FOOD HALL UPTOWN PLACE MALL BONIFACIO GL FORT BONIFACIO NCR, FOURTH DISTRICT TAGUIG CITY",
             "Location"=> "NCR",
             "Date Open"=> "05-20-2016",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00096",
             "Branch Name"=> "SM TARLAC",
             "Short Name"=> "TLC",
             "Mall Admin"=> "SM MALL",
             "Address"=> "2F SPACE 201&202A SM CITY MC ARTHUR HIGHWAY SAN ROQUE TARLAC CITY",
             "Location"=> "REGION III",
             "Date Open"=> "04-30-2010",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00104",
             "Branch Name"=> "WALTER MART STA. MARIA",
             "Short Name"=> "WSM",
             "Mall Admin"=> "WALTERMART",
             "Address"=> "GROUND FLOOR,WALTERMART CENTER-STA. MARIA PROVINCIAL ROAD CORNER BYPASS ROAD, BRGY. STA. CLARA, STA. MARIA, BULACAN",
             "Location"=> "REGION III",
             "Date Open"=> "01-12-2013",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00065",
             "Branch Name"=> "SM DASMARIÑAS",
             "Short Name"=> "DSM",
             "Mall Admin"=> "SM MALL",
             "Address"=> "UPPER GROUND FLOOR 134, SM CITY DASMARINAS, GOVERNORS DRIVE BRGY. SAMPALOK I, DASMARINAS, CAVITE",
             "Location"=> "REGION IVA",
             "Date Open"=> "04-12-2013",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00098",
             "Branch Name"=> "SM VALENZUELA",
             "Short Name"=> "VLZ",
             "Mall Admin"=> "SM MALL",
             "Address"=> "UNIT 104A1 SM CENTER VALENZUEA BRGY. KARUHATAN, VALENZUELA METRO MANILA",
             "Location"=> "NCR",
             "Date Open"=> "09-16-2011",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00094",
             "Branch Name"=> "SM STA. ROSA",
             "Short Name"=> "SRS",
             "Mall Admin"=> "SM MALL",
             "Address"=> "UNIT 1051 SM CITY SANTA. ROSA, NATIONAL ROAD, BARRIO TAGAPO, SANTA ROSA CITY, LAGUNA",
             "Location"=> "REGION IVA",
             "Date Open"=> "03-01-2013",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00066",
             "Branch Name"=> "SM DAVAO",
             "Short Name"=> "DAV",
             "Mall Admin"=> "SM MALL",
             "Address"=> "UNIT 122A SM CITY DAVAO QUIMPO BLVD. TULIP DRIVE ECOLAND DAVAO CITY",
             "Location"=> "REGION XI",
             "Date Open"=> "02-18-2011",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00063",
             "Branch Name"=> "SM CLARK",
             "Short Name"=> "CLA",
             "Mall Admin"=> "SM MALL",
             "Address"=> "UNIT 129 GF SM CITY CLARK MALABANAS, ANGELES CITY",
             "Location"=> "REGION III",
             "Date Open"=> "12-19-2008",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00064",
             "Branch Name"=> "SM CONSOLACION",
             "Short Name"=> "CSN",
             "Mall Admin"=> "SM MALL",
             "Address"=> "GF UNIT LC 173 SM CITY CONSOLACION, NATIONAL RD BRGY LANAC CONSOLACION CEBU",
             "Location"=> "REGION VII",
             "Date Open"=> "07-11-2015",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00072",
             "Branch Name"=> "SM LIPA",
             "Short Name"=> "LIP",
             "Mall Admin"=> "SM MALL",
             "Address"=> "SMLP1009 SM CITY LIPA, LIPA CITY BATANGAS",
             "Location"=> "REGION IVA",
             "Date Open"=> "05-15-2009",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00073",
             "Branch Name"=> "SM LUCENA",
             "Short Name"=> "LUC",
             "Mall Admin"=> "SM MALL",
             "Address"=> "UNIT 105-106 SM CITY LUCENA DALAHICAN CORNER MAHARLIKA HIGHWAY, NATIONAL ROAD, LUCENA CITY",
             "Location"=> "REGION IVA",
             "Date Open"=> "10-25-2013",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00092",
             "Branch Name"=> "SM SHELL RESIDENCES",
             "Short Name"=> "SRE",
             "Mall Admin"=> "SM RESIDENCES",
             "Address"=> "GROUND FLOOR UNIT 119-120 SMDC SHELL RESIDENCES EDSA CORNER ROAD 11 SM MALL OF ASIA BRGY. 76 PASAYA CITY ",
             "Location"=> "NCR",
             "Date Open"=> "06-24-2016",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00112",
             "Branch Name"=> "SM SUCAT 2",
             "Short Name"=> "SCT2",
             "Mall Admin"=> "SM MALL",
             "Address"=> "GROUND FLOOR UNIT R111-R112, SM CITY SUCAT, SAN DIONISIO, CITY OF PARAÑAQUE NCR, FOURTH DISTRICT PHILIPPINES 1700",
             "Location"=> "NCR",
             "Date Open"=> "08-15-2017",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00101",
             "Branch Name"=> "TRINOMA LANDMARK",
             "Short Name"=> "TRI",
             "Mall Admin"=> "AYALA MALL",
             "Address"=> "UNIT 8 FOOS CENTRAL LEVEL LANDMARK TRINOMA",
             "Location"=> "NCR",
             "Date Open"=> "04-09-2010",
             "Status"=> "inactive"
            ],
            [
             "Branch ID"=> "SFI00030",
             "Branch Name"=> "CASH & CARRY MALL",
             "Short Name"=> "CNC",
             "Mall Admin"=> "OTHER",
             "Address"=> "UNIT G23-G23A-GM11 CASH AND CARRY MALL SOUTH SUPERHIGHWAY COR EMILIA ST. PALANAN MAKATI CITY",
             "Location"=> "NCR",
             "Date Open"=> "06-05-2009",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00115",
             "Branch Name"=> "SM TELABASTAGAN",
             "Short Name"=> "TEL",
             "Mall Admin"=> "SM MALL",
             "Address"=> "2-F UNIT 213 SM CITY TELABASTAGAN CITY OF SAN FERNANDO (CAPITAL) PAMPAMPANGA",
             "Location"=> "REGION III",
             "Date Open"=> "06-04-2018",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00105",
             "Branch Name"=> "AYALA TUTUBAN CENTER",
             "Short Name"=> "TTB",
             "Mall Admin"=> "AYALA MALL",
             "Address"=> "GROUND FLOOR UNIT M2-GO28C, CENTERMALL BUILDING TUTUBAN CENTER, RECTO AVENUE BRGY. 48, TONDO I - II NCR, CITY OF MANILA, FIRST DISTRICT PHILIPPINES 1012",
             "Location"=> "NCR",
             "Date Open"=> "04-17-2017",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00061",
             "Branch Name"=> "SM CDO",
             "Short Name"=> "CDO",
             "Mall Admin"=> "SM MALL",
             "Address"=> "STALL 121-122 SM CITY CAGAYAN DE ORO MASTERSON AVE COR GRAN VIA ST., CAGAYAN DE ORO CITY",
             "Location"=> "REGION X",
             "Date Open"=> "01-30-2012",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00106",
             "Branch Name"=> "SM CDO 2",
             "Short Name"=> "CDO2",
             "Mall Admin"=> "SM MALL",
             "Address"=> "UNIT 304 SM CDO DOWNTOWN PREMIER BARANGAY 24 (POB.) , CAGAYAN DE ORO CITY (CAPITAL) MISAMIS ORIENTAL",
             "Location"=> "REGION X",
             "Date Open"=> "05-11-2017",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00002",
             "Branch Name"=> "ACCOUNTING DEPARTMENT",
             "Short Name"=> "ACD",
             "Mall Admin"=> "NONE",
             "Address"=> "NO. 84 STO. DOMINGO ST., COR. N.S. AMORANTO, QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "05-10-2007",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00003",
             "Branch Name"=> "ALHAMBRA WAREHOUSE",
             "Short Name"=> "ALH",
             "Mall Admin"=> "NONE",
             "Address"=> "NO. 3 ALHAMBRA ST., DAMAYAN, QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "05-10-2007",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00004",
             "Branch Name"=> "AUDITING DEPARTMENT",
             "Short Name"=> "AUD",
             "Mall Admin"=> "NONE",
             "Address"=> "NO. 84 STO. DOMINGO ST., COR. N.S. AMORANTO, QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "05-10-2007",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00006",
             "Branch Name"=> "HUMAN RESOURCES DEPARTMENT",
             "Short Name"=> "HRD",
             "Mall Admin"=> "NONE",
             "Address"=> "NO. 84 STO. DOMINGO ST., COR. N.S. AMORANTO, QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "05-10-2007",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00005",
             "Branch Name"=> "FLORENCIA WAREHOUSE",
             "Short Name"=> "FLW",
             "Mall Admin"=> "NONE",
             "Address"=> "NO. 28 FLORENCIA ST., SAN FRANCISCO DEL MONTE, QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "05-10-2007",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00014",
             "Branch Name"=> "NEW PROJECT ACCOUNT",
             "Short Name"=> "NPA",
             "Mall Admin"=> "NONE",
             "Address"=> "NO. 84 STO. DOMINGO ST., COR. N.S. AMORANTO, QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "05-10-2007",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00097",
             "Branch Name"=> "SM TAYTAY",
             "Short Name"=> "TAY",
             "Mall Admin"=> "SM MALL",
             "Address"=> "UNIT 106(A) SM CITY TAYTAY MANILA EAST ROAD, BRGY. DOLORES,TAYTAY RIZAL",
             "Location"=> "REGION IVA",
             "Date Open"=> "10-01-2011",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00015",
             "Branch Name"=> "SFI - BUILDING",
             "Short Name"=> "BDG",
             "Mall Admin"=> "NONE",
             "Address"=> "NO. 84 STO. DOMINGO ST., COR. N.S. AMORANTO, QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "05-10-2007",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00121",
             "Branch Name"=> "AYALA BAY AREA",
             "Short Name"=> "BAY",
             "Mall Admin"=> "OTHER",
             "Address"=> "ASEANA COR. MACAPAGAL AVENUE PARAÑAQUE CITY",
             "Location"=> "NCR",
             "Date Open"=> "08-22-2018",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00118",
             "Branch Name"=> "MEGAWORLD ILOILO",
             "Short Name"=> "MIL",
             "Mall Admin"=> "OTHER",
             "Address"=> "NO. 84 STO. DOMINGO ST., COR. N.S. AMORANTO, QUEZON CITY",
             "Location"=> "REGION VI",
             "Date Open"=> "06-04-2018",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00010",
             "Branch Name"=> "OPERATIONS DEPARTMENT - BRANCH",
             "Short Name"=> "OPD",
             "Mall Admin"=> "NONE",
             "Address"=> "NO. 84 STO. DOMINGO ST., COR. N.S. AMORANTO, QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "05-10-2007",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00120",
             "Branch Name"=> "SM LEGAZPI",
             "Short Name"=> "LGP",
             "Mall Admin"=> "SM MALL",
             "Address"=> "Legazpi City Albay",
             "Location"=> "REGION V",
             "Date Open"=> "06-18-2018",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00056",
             "Branch Name"=> "SM BAGUIO",
             "Short Name"=> "BAG",
             "Mall Admin"=> "SM MALL",
             "Address"=> "132 UPPER GROUND FLOOR, SM CITY BAGUIO, UPPER SESSION ROAD, LUNETA HILL DR, BAGUIO, BENGUET",
             "Location"=> "CAR",
             "Date Open"=> "05-30-2009",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00122",
             "Branch Name"=> "OPERATIONS DEPARTMENT - HO",
             "Short Name"=> "OPD2",
             "Mall Admin"=> "NONE",
             "Address"=> "NO. 84 STO. DOMINGO",
             "Location"=> "NCR",
             "Date Open"=> "09-08-2018",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00036",
             "Branch Name"=> "GREENHILLS THEATRE MALL",
             "Short Name"=> "GHT",
             "Mall Admin"=> "OTHER",
             "Address"=> "UNIT F&B-6 MEZZANINE ARTS PLAZA LOBBY GREENHILLS THEATRE MALL, GREENHILLS SAN JUAN CITY",
             "Location"=> "NCR",
             "Date Open"=> "03-06-2009",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00091",
             "Branch Name"=> "SM SEASIDE",
             "Short Name"=> "SEA",
             "Mall Admin"=> "SM MALL",
             "Address"=> "LGF UNIT 0109-0110 SM SEA SIDE CITY CEBU SOUTH ROAD CENTRAL, CEBU CITY",
             "Location"=> "REGION VII",
             "Date Open"=> "11-26-2015",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00128",
             "Branch Name"=> "SM CENTER DAGUPAN",
             "Short Name"=> "DGP",
             "Mall Admin"=> "SM MALL",
             "Address"=> "2F UNIT 2019, 272A SM CENTER DAGUPAN M.H DEL PILAR ST. BRGY. HERRERO - PEREZ DAGUPAN CITY",
             "Location"=> "REGION I",
             "Date Open"=> "06-18-2019",
             "Status"=> "inactive"
            ],
            [
             "Branch ID"=> "SFI00116",
             "Branch Name"=> "SM URDANETA",
             "Short Name"=> "URD",
             "Mall Admin"=> "SM MALL",
             "Address"=> "2-F UNIT 260-261B SM CITY URDANETA CENTRAL NANCAYASAN CITY OF URDANETA PANGASINAN",
             "Location"=> "REGION I",
             "Date Open"=> "06-04-2018",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00126",
             "Branch Name"=> "MARKET MARKET",
             "Short Name"=> "MMT",
             "Mall Admin"=> "OTHER",
             "Address"=> "GROUND FL. UNIT RR6 MARKET MARKET MALL TAGUIG CITY",
             "Location"=> "NCR",
             "Date Open"=> "03-29-2019",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00127",
             "Branch Name"=> "SM OLONGAPO 2",
             "Short Name"=> "SCO",
             "Mall Admin"=> "SM MALL",
             "Address"=> "2F Unit 204-205 SM City Olongapo Central, Rizal Ave., Brgy. East Tapinac Olongapo City",
             "Location"=> "REGION III",
             "Date Open"=> "06-03-2019",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00129",
             "Branch Name"=> "AIRSPEED PHILIPPINES INC.",
             "Short Name"=> "AIR",
             "Mall Admin"=> "OTHER",
             "Address"=> "C5 Extension, Brgy. La Huerta, Parañaque City",
             "Location"=> "NCR",
             "Date Open"=> "11-12-2019",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00130",
             "Branch Name"=> "METRO COMBINED LOGISTICS, INC.",
             "Short Name"=> "MCL",
             "Mall Admin"=> "OTHER",
             "Address"=> "#56 Elisco Road, cor. Axis Road, Pasig City",
             "Location"=> "NCR",
             "Date Open"=> "11-12-2019",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00123",
             "Branch Name"=> "COMMISSARY WAREHOUSE",
             "Short Name"=> "COM",
             "Mall Admin"=> "NONE",
             "Address"=> "No. 84 Sto. Domingo St. Cor. N.S. Amoranto, Quezon City",
             "Location"=> "NCR",
             "Date Open"=> "09-21-2018",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00131",
             "Branch Name"=> "SM DAET",
             "Short Name"=> "DAE",
             "Mall Admin"=> "SM MALL",
             "Address"=> "Brgy. Lag-on, Daet, Camarines Norte",
             "Location"=> "REGION V",
             "Date Open"=> "02-07-2020",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00136",
             "Branch Name"=> "SM TANZA",
             "Short Name"=> "TZA",
             "Mall Admin"=> "SM MALL",
             "Address"=> "UNIT 1017-1018 SM CITY TANZA ANTERO SORIANO HIGHWAY, BARANGAY DAANG AMAYA ll,TANZA CAVITE",
             "Location"=> "REGION IVA",
             "Date Open"=> "06-21-2022",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00132",
             "Branch Name"=> "AYALA SERIN",
             "Short Name"=> "SER",
             "Mall Admin"=> "AYALA MALL",
             "Address"=> "GF 53 AYALA MALLS SERIN BRGY SILANG CROSSING EAST TAGAYTAY",
             "Location"=> "REGION IVA",
             "Date Open"=> "01-25-2022",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00022",
             "Branch Name"=> "AYALA LCC LEGAZPI",
             "Short Name"=> "LEG",
             "Mall Admin"=> "AYALA MALL",
             "Address"=> "2ND FLOOR UNIT 206-207 LIBERTY CITY CENTER BGY. 36 - KAPANTAWAN (POB), LEGAZPI",
             "Location"=> "REGION V",
             "Date Open"=> "04-08-2016",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00050",
             "Branch Name"=> "ROBINSONS PALAWAN",
             "Short Name"=> "PWN",
             "Mall Admin"=> "ROBINSONS MALL",
             "Address"=> "LEVEL 1- 00184 TO 00185 ROBINSONS PLACE PALAWAN, BRGY. SAN MANUEL, PUERTO PRINCESA, PALAWAN",
             "Location"=> "REGION VI",
             "Date Open"=> "12-30-2012",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00137",
             "Branch Name"=> "CAVITE WAREHOUSE",
             "Short Name"=> "CAV",
             "Mall Admin"=> "NONE",
             "Address"=> " BLOCK 12 LOT 11 CAVITE LIGHT INDUSTRIAL PARK BRGY. MAGUYAM, SILANG CAVITE",
             "Location"=> "REGION IVA",
             "Date Open"=> "08-17-2022",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00044",
             "Branch Name"=> "ROBINSONS ILIGAN",
             "Short Name"=> "ILI",
             "Mall Admin"=> "ROBINSONS MALL",
             "Address"=> "LEVEL 1, L1 111-112 ROBINSONS PLACE ILIGAN, MACAPAGAL AVE. TUBOD, ILIGAN CITY LANAO DEL NORTE PHILIPPINES 9200",
             "Location"=> "REGION X",
             "Date Open"=> "09-29-2017",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00133",
             "Branch Name"=> "GAISANO MALL DAVAO",
             "Short Name"=> "GMD",
             "Mall Admin"=> "OTHER",
             "Address"=> "UGF 5 GMALL DAVAO JP LAUREL BAJADA DAVAO CITY",
             "Location"=> "REGION XI",
             "Date Open"=> "02-25-2022",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00143",
             "Branch Name"=> "SUPERIOR PRODUCT SOLUTIONS TRADING, INC.",
             "Short Name"=> "SUP",
             "Mall Admin"=> "OTHER",
             "Address"=> "UNIT 203 J & F BUILIDING 2, V.V. SOLIVEN AVE. 3 CAINTA RIZAL",
             "Location"=> "REGION IVA",
             "Date Open"=> "02-27-2023",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00145",
             "Branch Name"=> "TRAINING DEPARTMENT",
             "Short Name"=> "TRA",
             "Mall Admin"=> "NONE",
             "Address"=> "NO.84 STO DOMINGO ST., BRGY STO DOMINGO QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "05-05-2023",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00145",
             "Branch Name"=> "TRAINING DEPARTMENT",
             "Short Name"=> "TRA",
             "Mall Admin"=> "NONE",
             "Address"=> "NO.84 STO DOMINGO ST., BRGY STO DOMINGO QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "05-05-2023",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00145",
             "Branch Name"=> "TRAINING DEPARTMENT",
             "Short Name"=> "TRA",
             "Mall Admin"=> "NONE",
             "Address"=> "NO.84 STO DOMINGO ST., BRGY STO DOMINGO QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "05-05-2023",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00113",
             "Branch Name"=> "SM IMUS",
             "Short Name"=> "SIM",
             "Mall Admin"=> "SM MALL",
             "Address"=> "G-F UNIT 140A,149,150A SM CENTER IMUS NIA ROAD ALAPAN I-C, IMUS CAVITE PHILIPPINES 4103",
             "Location"=> "REGION IVA",
             "Date Open"=> "02-16-2018",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00070",
             "Branch Name"=> "SM LANANG",
             "Short Name"=> "LNG",
             "Mall Admin"=> "SM MALL",
             "Address"=> "LEVEL 3, UNIT 337-338 SM LANANG PREMIER, J.P. LAUREL AVENUE, BAJADA, LANANG DAVAO CITY",
             "Location"=> "REGION XI",
             "Date Open"=> "09-27-2012",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00111",
             "Branch Name"=> "SM PUERTO PRINCESA",
             "Short Name"=> "PRI",
             "Mall Admin"=> "SM MALL",
             "Address"=> "UPPER GROUND FLOOR UNIT 140-141, SM CITY PUERTO PRINCES, MALVAR ROAD COR. LACAO ST. SAN MIGUEL, PUERTO PRINCESA CITY PALAWAN PHILIPPINES 5300",
             "Location"=> "REGION IVB",
             "Date Open"=> "09-14-2017",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00069",
             "Branch Name"=> "SM ILOILO",
             "Short Name"=> "SIL",
             "Mall Admin"=> "SM MALL",
             "Address"=> "2ND FLOOR UNIT 2013-2014 SM CITY ILOILO BOLILAO, ILOILO CITY (CAPITAL) ILOILO",
             "Location"=> "REGION VI",
             "Date Open"=> "06-17-2016",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00142",
             "Branch Name"=> "SM CITY BATAAN",
             "Short Name"=> "BAT",
             "Mall Admin"=> "SM MALL",
             "Address"=> "GF Unit 1123,1124a SM City Bataan, Lerma St. Brgy. Ibayo, Balanga City, Bataan.",
             "Location"=> "REGION III",
             "Date Open"=> "01-24-2023",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00142",
             "Branch Name"=> "SM CITY BATAAN",
             "Short Name"=> "BAT",
             "Mall Admin"=> "SM MALL",
             "Address"=> "GF Unit 1123,1124a SM City Bataan, Lerma St. Brgy. Ibayo, Balanga City, Bataan.",
             "Location"=> "REGION III",
             "Date Open"=> "01-24-2023",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00138",
             "Branch Name"=> "NEW GATEWAY MALL II",
             "Short Name"=> "GWY",
             "Mall Admin"=> "OTHER",
             "Address"=> "Upper Ground Flr. B Space No. 11, Araneta City, Quezon City",
             "Location"=> "NCR",
             "Date Open"=> "10-14-2022",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00138",
             "Branch Name"=> "NEW GATEWAY MALL II",
             "Short Name"=> "GWY",
             "Mall Admin"=> "OTHER",
             "Address"=> "Upper Ground Flr. B Space No. 11, Araneta City, Quezon City",
             "Location"=> "NCR",
             "Date Open"=> "10-14-2022",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00023",
             "Branch Name"=> "AYALA MARQUEE MALL",
             "Short Name"=> "MQE",
             "Mall Admin"=> "AYALA MALL",
             "Address"=> "SPACE 2097 2ND FLOOR MARQUEE MALL FRANCISCO G. NEPO AVE. ANGELES CITY",
             "Location"=> "REGION III",
             "Date Open"=> "10-30-2009",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00088",
             "Branch Name"=> "SM SAN FERNANDO",
             "Short Name"=> "SF2",
             "Mall Admin"=> "SM MALL",
             "Address"=> "UNIT 303, SM CITY SAN FERNANDO, V. TIOMICO ST. BRGY. STO. ROSARIO, SAN FERNANDO CITY PAMPANGA",
             "Location"=> "REGION III",
             "Date Open"=> "03-03-2013",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00088",
             "Branch Name"=> "SM SAN FERNANDO",
             "Short Name"=> "SF2",
             "Mall Admin"=> "SM MALL",
             "Address"=> "UNIT 303, SM CITY SAN FERNANDO, V. TIOMICO ST. BRGY. STO. ROSARIO, SAN FERNANDO CITY PAMPANGA",
             "Location"=> "REGION III",
             "Date Open"=> "03-03-2013",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00088",
             "Branch Name"=> "SM SAN FERNANDO",
             "Short Name"=> "SF2",
             "Mall Admin"=> "SM MALL",
             "Address"=> "UNIT 303, SM CITY SAN FERNANDO, V. TIOMICO ST. BRGY. STO. ROSARIO, SAN FERNANDO CITY PAMPANGA",
             "Location"=> "REGION III",
             "Date Open"=> "03-03-2013",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00125",
             "Branch Name"=> "ROBINSONS SAN PEDRO",
             "Short Name"=> "RSP",
             "Mall Admin"=> "ROBINSONS MALL",
             "Address"=> "LVL2 UNIT256,257 ROBINSON GALLERIA SOUTH SAN PEDRO CITY LAGUNA",
             "Location"=> "REGION IVA",
             "Date Open"=> "03-29-2019",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00125",
             "Branch Name"=> "ROBINSONS SAN PEDRO",
             "Short Name"=> "RSP",
             "Mall Admin"=> "ROBINSONS MALL",
             "Address"=> "LVL2 UNIT256,257 ROBINSON GALLERIA SOUTH SAN PEDRO CITY LAGUNA",
             "Location"=> "REGION IVA",
             "Date Open"=> "03-29-2019",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00043",
             "Branch Name"=> "ROBINSONS GALLERIA ORTIGAS",
             "Short Name"=> "GAL",
             "Mall Admin"=> "ROBINSONS MALL",
             "Address"=> "LEVEL 2 UNIT 02329 ROBINSONS GALLERIA EDSA CORNER ORTIGAS AVE.,QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "07-17-2012",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00043",
             "Branch Name"=> "ROBINSONS GALLERIA ORTIGAS",
             "Short Name"=> "GAL",
             "Mall Admin"=> "ROBINSONS MALL",
             "Address"=> "LEVEL 2 UNIT 02329 ROBINSONS GALLERIA EDSA CORNER ORTIGAS AVE.,QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "07-17-2012",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00043",
             "Branch Name"=> "ROBINSONS GALLERIA ORTIGAS",
             "Short Name"=> "GAL",
             "Mall Admin"=> "ROBINSONS MALL",
             "Address"=> "LEVEL 2 UNIT 02329 ROBINSONS GALLERIA EDSA CORNER ORTIGAS AVE.,QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "07-17-2012",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00140",
             "Branch Name"=> "ONE AYALA MALL",
             "Short Name"=> "OAY",
             "Mall Admin"=> "AYALA MALL",
             "Address"=> "Unit C-013 to C-014, Lower Ground Floor, One Ayala",
             "Location"=> "NCR",
             "Date Open"=> "11-23-2022",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00140",
             "Branch Name"=> "ONE AYALA MALL",
             "Short Name"=> "OAY",
             "Mall Admin"=> "AYALA MALL",
             "Address"=> "Unit C-013 to C-014, Lower Ground Floor, One Ayala",
             "Location"=> "NCR",
             "Date Open"=> "11-23-2022",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00114",
             "Branch Name"=> "FORA MALL TAGAYTAY",
             "Short Name"=> "FOR",
             "Mall Admin"=> "OTHER",
             "Address"=> "UGF UNIT 2064-2065 FORA MALL EMILIO AGUINALDO HIGHWAY ROTUN SILANG JUNCTION NORTH TAGAYTAY CITY CAVITE",
             "Location"=> "REGION IVA",
             "Date Open"=> "02-17-2017",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00028",
             "Branch Name"=> "AYALA THE LINK",
             "Short Name"=> "LNK",
             "Mall Admin"=> "AYALA MALL",
             "Address"=> "FOOD 1-008 LEVEL 1 THE LINK FOOD CHOICES AYALA CENTER MAKATI CITY 1224",
             "Location"=> "NCR",
             "Date Open"=> "11-06-2009",
             "Status"=> "inactive"
            ],
            [
             "Branch ID"=> "SFI00134",
             "Branch Name"=> "ROBINSONS ANTIPOLO",
             "Short Name"=> "RAN",
             "Mall Admin"=> "ROBINSONS MALL",
             "Address"=> "UG UNIT 235A, 235B  ROBINSONS PLACE ANTIPOLO SUMULONG HIGHWAY, L. SUMULONG MEMORIAL CIRCLE, ANTIPOLO CITY",
             "Location"=> "REGION IVA",
             "Date Open"=> "02-25-2022",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00134",
             "Branch Name"=> "ROBINSONS ANTIPOLO",
             "Short Name"=> "RAN",
             "Mall Admin"=> "ROBINSONS MALL",
             "Address"=> "UG UNIT 235A, 235B  ROBINSONS PLACE ANTIPOLO SUMULONG HIGHWAY, L. SUMULONG MEMORIAL CIRCLE, ANTIPOLO CITY",
             "Location"=> "REGION IVA",
             "Date Open"=> "02-25-2022",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00047",
             "Branch Name"=> "ROBINSONS MAGNOLIA",
             "Short Name"=> "MAG",
             "Mall Admin"=> "ROBINSONS MALL",
             "Address"=> "LOWER GROUND LG013 AND LG014 ROBINSONS MAGNOLIA, AURORA BLVD., QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "08-13-2012",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00047",
             "Branch Name"=> "ROBINSONS MAGNOLIA",
             "Short Name"=> "MAG",
             "Mall Admin"=> "ROBINSONS MALL",
             "Address"=> "LOWER GROUND LG013 AND LG014 ROBINSONS MAGNOLIA, AURORA BLVD., QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "08-13-2012",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00046",
             "Branch Name"=> "ROBINSONS ILOILO",
             "Short Name"=> "ILO",
             "Mall Admin"=> "ROBINSONS MALL",
             "Address"=> "UNIT 1-01171, ROBINSONS PLACE ILOILO, CORNER DE LEON - QUEZON ST., ILOILO CITY, ILOILO",
             "Location"=> "REGION VI",
             "Date Open"=> "09-06-2013",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00046",
             "Branch Name"=> "ROBINSONS ILOILO",
             "Short Name"=> "ILO",
             "Mall Admin"=> "ROBINSONS MALL",
             "Address"=> "UNIT 1-01171, ROBINSONS PLACE ILOILO, CORNER DE LEON - QUEZON ST., ILOILO CITY, ILOILO",
             "Location"=> "REGION VI",
             "Date Open"=> "09-06-2013",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00107",
             "Branch Name"=> "SM GRACE RESIDENCES",
             "Short Name"=> "GRA",
             "Mall Admin"=> "SM MALL",
             "Address"=> "UNIT 107-108 SMDC GRACE RESIDENCES TOWER 4 USUSAN, TAGUIG CITY NCR, FOURTH DISTRICT PHILIPPINES 1632",
             "Location"=> "NCR",
             "Date Open"=> "06-29-2017",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00107",
             "Branch Name"=> "SM GRACE RESIDENCES",
             "Short Name"=> "GRA",
             "Mall Admin"=> "SM MALL",
             "Address"=> "UNIT 107-108 SMDC GRACE RESIDENCES TOWER 4 USUSAN, TAGUIG CITY NCR, FOURTH DISTRICT PHILIPPINES 1632",
             "Location"=> "NCR",
             "Date Open"=> "06-29-2017",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00045",
             "Branch Name"=> "ROBINSONS ILOCOS",
             "Short Name"=> "COS",
             "Mall Admin"=> "ROBINSONS MALL",
             "Address"=> "UNIT NO. 162-163, LEVEL 1 CCD ROBINSONS PLACE ILOCOS SAN FRANCISCO (POB.), SAN NICOLAS ILOCOS NORTE",
             "Location"=> "REGION I",
             "Date Open"=> "02-17-2017",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00045",
             "Branch Name"=> "ROBINSONS ILOCOS",
             "Short Name"=> "COS",
             "Mall Admin"=> "ROBINSONS MALL",
             "Address"=> "UNIT NO. 162-163, LEVEL 1 CCD ROBINSONS PLACE ILOCOS SAN FRANCISCO (POB.), SAN NICOLAS ILOCOS NORTE",
             "Location"=> "REGION I",
             "Date Open"=> "02-17-2017",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00025",
             "Branch Name"=> "AYALA SOLENAD 3",
             "Short Name"=> "SOL",
             "Mall Admin"=> "AYALA MALL",
             "Address"=> "GF UNIT 23.24.25SOLENAD 3 NOVALI STA. ROSA TAGAYTAY ROAD, BRGY STO DOMINGO, STA ROSA LAGUNA",
             "Location"=> "REGION IVA",
             "Date Open"=> "08-07-2015",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00025",
             "Branch Name"=> "AYALA SOLENAD 3",
             "Short Name"=> "SOL",
             "Mall Admin"=> "AYALA MALL",
             "Address"=> "GF UNIT 23.24.25SOLENAD 3 NOVALI STA. ROSA TAGAYTAY ROAD, BRGY STO DOMINGO, STA ROSA LAGUNA",
             "Location"=> "REGION IVA",
             "Date Open"=> "08-07-2015",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00135",
             "Branch Name"=> "AUBURN LEAF CONSOLIDATED CORP.",
             "Short Name"=> "AUB",
             "Mall Admin"=> "NONE",
             "Address"=> "15 Catmon St., Brgy. Talayan, Talayan Village, Quezon City",
             "Location"=> "NCR",
             "Date Open"=> "04-29-2022",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00135",
             "Branch Name"=> "AUBURN LEAF CONSOLIDATED CORP.",
             "Short Name"=> "AUB",
             "Mall Admin"=> "NONE",
             "Address"=> "15 Catmon St., Brgy. Talayan, Talayan Village, Quezon City",
             "Location"=> "NCR",
             "Date Open"=> "04-29-2022",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00019",
             "Branch Name"=> "AYALA CENTRIO MALL",
             "Short Name"=> "CEN",
             "Mall Admin"=> "AYALA MALL",
             "Address"=> "LEVEL 3 SPACE 3047 CENTRIO MALL CM RECTO AVE., CAGAYAN DE ORO CITY",
             "Location"=> "REGION XI",
             "Date Open"=> "11-30-2013",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00042",
             "Branch Name"=> "ROBINSONS GALLERIA CEBU",
             "Short Name"=> "RGC",
             "Mall Admin"=> "ROBINSONS MALL",
             "Address"=> "LEVEL 3 UNIT 3035 ROBINSONS GALLERIA CEBU MAXILOM-OSMENA BLVD.  13TH AVENUE & BENEDICTO ST. NORTH CEBU CITY",
             "Location"=> "REGION VII",
             "Date Open"=> "01-21-2016",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00139",
             "Branch Name"=> "ROBINSONS STARMILLS",
             "Short Name"=> "RSM",
             "Mall Admin"=> "ROBINSONS MALL",
             "Address"=> "LEVEL 1, OLONGAPO-GAPAN ROAD, BARANGAY SAN JOSE, CITY OF SAN FERNANDO, PAMPANGA",
             "Location"=> "REGION III",
             "Date Open"=> "11-08-2022",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00139",
             "Branch Name"=> "ROBINSONS STARMILLS",
             "Short Name"=> "RSM",
             "Mall Admin"=> "ROBINSONS MALL",
             "Address"=> "LEVEL 1, OLONGAPO-GAPAN ROAD, BARANGAY SAN JOSE, CITY OF SAN FERNANDO, PAMPANGA",
             "Location"=> "REGION III",
             "Date Open"=> "11-08-2022",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00146",
             "Branch Name"=> "SM CENTER SAN PEDRO",
             "Short Name"=> "CSP",
             "Mall Admin"=> "SM MALL",
             "Address"=> "LG 13B-14 SM CENTER SAN PEDRO",
             "Location"=> "REGION IVA",
             "Date Open"=> "05-23-2023",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00146",
             "Branch Name"=> "SM CENTER SAN PEDRO",
             "Short Name"=> "CSP",
             "Mall Admin"=> "SM MALL",
             "Address"=> "LG 13B-14 SM CENTER SAN PEDRO",
             "Location"=> "REGION IVA",
             "Date Open"=> "05-23-2023",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00144",
             "Branch Name"=> "SM CITY STO. TOMAS",
             "Short Name"=> "SST",
             "Mall Admin"=> "SM MALL",
             "Address"=> "UNIT 2017, SM CITY STO. TOMAS, PAN-PHILIPPINE HWY, SAN ROQUE, SANTO TOMAS, BATANGAS",
             "Location"=> "REGION IVA",
             "Date Open"=> "04-26-2023",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00144",
             "Branch Name"=> "SM CITY STO. TOMAS",
             "Short Name"=> "SST",
             "Mall Admin"=> "SM MALL",
             "Address"=> "UNIT 2017, SM CITY STO. TOMAS, PAN-PHILIPPINE HWY, SAN ROQUE, SANTO TOMAS, BATANGAS",
             "Location"=> "REGION IVA",
             "Date Open"=> "04-26-2023",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00147",
             "Branch Name"=> "SM HYPERMARKET SUCAT",
             "Short Name"=> "HMS",
             "Mall Admin"=> "SM HYPERMART",
             "Address"=> "UNIT 105, SM HYPERMARKET SUCAT LOPEZ, DR ARCADIO SANTOS AVE., SAN ISIDRO PARANAQUE",
             "Location"=> "NCR",
             "Date Open"=> "06-22-2023",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00099",
             "Branch Name"=> "STA. LUCIA MALL",
             "Short Name"=> "SLM",
             "Mall Admin"=> "OTHER",
             "Address"=> "G3-03A GROUND LEVEL, BLDG. III, STA. LUCIA MALL, MARCOS HIGHWAY COR., FELIX AVE., CAINTA, RIZAL",
             "Location"=> "REGION IVA",
             "Date Open"=> "03-07-2014",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00141",
             "Branch Name"=> "GREENHILLS 2",
             "Short Name"=> "GH2",
             "Mall Admin"=> "OTHER",
             "Address"=> "Ortigas&Company, 2-F Officenter, Greenhills Center, San Juan City",
             "Location"=> "NCR",
             "Date Open"=> "01-24-2023",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00001",
             "Branch Name"=> "Head Office",
             "Short Name"=> "HO",
             "Mall Admin"=> "NONE",
             "Address"=> "NO. 84 STO. DOMINGO ST., COR. N.S. AMORANTO, QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "05-10-2007",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00001",
             "Branch Name"=> "Head Office",
             "Short Name"=> "HO",
             "Mall Admin"=> "NONE",
             "Address"=> "NO. 84 STO. DOMINGO ST., COR. N.S. AMORANTO, QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "05-10-2007",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00001",
             "Branch Name"=> "Head Office",
             "Short Name"=> "HO",
             "Mall Admin"=> "NONE",
             "Address"=> "NO. 84 STO. DOMINGO ST., COR. N.S. AMORANTO, QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "05-10-2007",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00001",
             "Branch Name"=> "Head Office",
             "Short Name"=> "HO",
             "Mall Admin"=> "NONE",
             "Address"=> "NO. 84 STO. DOMINGO ST., COR. N.S. AMORANTO, QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "05-10-2007",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00001",
             "Branch Name"=> "Head Office",
             "Short Name"=> "HO",
             "Mall Admin"=> "NONE",
             "Address"=> "NO. 84 STO. DOMINGO ST., COR. N.S. AMORANTO, QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "05-10-2007",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00001",
             "Branch Name"=> "Head Office",
             "Short Name"=> "HO",
             "Mall Admin"=> "NONE",
             "Address"=> "NO. 84 STO. DOMINGO ST., COR. N.S. AMORANTO, QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "05-10-2007",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00001",
             "Branch Name"=> "Head Office",
             "Short Name"=> "HO",
             "Mall Admin"=> "NONE",
             "Address"=> "NO. 84 STO. DOMINGO ST., COR. N.S. AMORANTO, QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "05-10-2007",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00001",
             "Branch Name"=> "Head Office",
             "Short Name"=> "HO",
             "Mall Admin"=> "NONE",
             "Address"=> "NO. 84 STO. DOMINGO ST., COR. N.S. AMORANTO, QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "05-10-2007",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00001",
             "Branch Name"=> "Head Office",
             "Short Name"=> "HO",
             "Mall Admin"=> "NONE",
             "Address"=> "NO. 84 STO. DOMINGO ST., COR. N.S. AMORANTO, QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "05-10-2007",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00001",
             "Branch Name"=> "Head Office",
             "Short Name"=> "HO",
             "Mall Admin"=> "NONE",
             "Address"=> "NO. 84 STO. DOMINGO ST., COR. N.S. AMORANTO, QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "05-10-2007",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00150",
             "Branch Name"=> "SM MEGAMALL 2",
             "Short Name"=> "MEG2",
             "Mall Admin"=> "SM MALL",
             "Address"=> "SM MEGAMALL ORTIGAS(UNIT 5&6) DONA JULIA VARGAS AVE. WACK WACK GREENHILLS MANDALUYONG CITY",
             "Location"=> "NCR",
             "Date Open"=> "04-19-2024",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00150",
             "Branch Name"=> "SM MEGAMALL 2",
             "Short Name"=> "MEG2",
             "Mall Admin"=> "SM MALL",
             "Address"=> "SM MEGAMALL ORTIGAS(UNIT 5&6) DONA JULIA VARGAS AVE. WACK WACK GREENHILLS MANDALUYONG CITY",
             "Location"=> "NCR",
             "Date Open"=> "04-19-2024",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00095",
             "Branch Name"=> "SM SUCAT",
             "Short Name"=> "SCT",
             "Mall Admin"=> "SM MALL",
             "Address"=> "GF UNIT  120-121(B) SM CITY SUCAT D.A. SANTOS AVE. SM DIONISIO PARANAQUE",
             "Location"=> "NCR",
             "Date Open"=> "01-05-2012",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00151",
             "Branch Name"=> "SM SORSOGON",
             "Short Name"=> "SOR",
             "Mall Admin"=> "SM MALL",
             "Address"=> "GF Unit 132-133 SM City Sorsogon, Maharlika Highway, Barangay Balogo, East District Sorsogon City",
             "Location"=> "REGION V",
             "Date Open"=> "04-24-2024",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00148",
             "Branch Name"=> "SM CALOOCAN",
             "Short Name"=> "CLN",
             "Mall Admin"=> "SM MALL",
             "Address"=> "UNIT 304-305, SM CITY CALOOCAN, DEPARO RD,BAGUMBONG CALOOCAN CITY",
             "Location"=> "NCR",
             "Date Open"=> "01-05-2024",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00119",
             "Branch Name"=> "UNIMART CAPITOL COMMONS",
             "Short Name"=> "UCC",
             "Mall Admin"=> "OTHER",
             "Address"=> "GF UNIMART CAPITOL COMMONS SHAW BLVD COR MERALCO AVE, ORANBO PASIG",
             "Location"=> "NCR",
             "Date Open"=> "06-04-2018",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00153",
             "Branch Name"=> "SM LAOAG",
             "Short Name"=> "SLG",
             "Mall Admin"=> "SM MALL",
             "Address"=> "LG UNIT 0122-0124, SM CITY LAOAG, LAOAG AIRPORT ROAD, BRGY. 51-B NANGALISAN WEST, LAOAG, ILOCOS NORTE",
             "Location"=> "REGION I",
             "Date Open"=> "06-14-2024",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00078",
             "Branch Name"=> "SM MEGAMALL",
             "Short Name"=> "MEG",
             "Mall Admin"=> "SM MALL",
             "Address"=> "SM MEGAMALL ORTIGAS(UNIT 5&6) DONA JULIA VARGAS AVE. WACK WACK GREENHILLS MANDALUYONG CITY",
             "Location"=> "NCR",
             "Date Open"=> "02-10-2010",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00152",
             "Branch Name"=> "ROBINSONS IMUS",
             "Short Name"=> "RIM",
             "Mall Admin"=> "ROBINSONS MALL",
             "Address"=> "LEVEL 1 01114, ROBINSONS PLACE IMUS, G. AGUINALDO HIGHWAY, TANZA LUMA V. IMUS CAVITE",
             "Location"=> "REGION IVA",
             "Date Open"=> "06-14-2024",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00152",
             "Branch Name"=> "ROBINSONS IMUS",
             "Short Name"=> "RIM",
             "Mall Admin"=> "ROBINSONS MALL",
             "Address"=> "LEVEL 1 01114, ROBINSONS PLACE IMUS, G. AGUINALDO HIGHWAY, TANZA LUMA V. IMUS CAVITE",
             "Location"=> "REGION IVA",
             "Date Open"=> "06-14-2024",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00117",
             "Branch Name"=> "AYALA FAIRVIEW TERRACES",
             "Short Name"=> "TER",
             "Mall Admin"=> "AYALA MALL",
             "Address"=> "UGF UNIT NO. U076 FAIRVIEW TERRACES QUIRINO HIGHWAY COR. MALIGAYA DRIVE NOVALICHES PASONG PUTIK PROPER (PASONG PUTIK), QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "06-04-2018",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00149",
             "Branch Name"=> "THE OUTLETS AT LIPA (inactive)",
             "Short Name"=> "TOL (OLD)",
             "Mall Admin"=> "OTHER",
             "Address"=> "BLOCK G UNIT R10 AND F07, THE OUTLETS AT LIPA, LIMA ESTATE, LIPA, BATANGAS",
             "Location"=> "REGION IVA",
             "Date Open"=> "02-14-2024",
             "Status"=> "inactive"
            ],
            [
             "Branch ID"=> "SFI00124",
             "Branch Name"=> "TAN IEUNG RESOURCES, INC.",
             "Short Name"=> "IEU",
             "Mall Admin"=> "NONE",
             "Address"=> "#15 CATMON ST., BRGY. TALAYAN, QUEZON CITY",
             "Location"=> "NCR",
             "Date Open"=> "12-14-2018",
             "Status"=> "active"
            ],
            [
             "Branch ID"=> "SFI00154",
             "Branch Name"=> "AYALA VERMOSA",
             "Short Name"=> "VMS",
             "Mall Admin"=> "AYALA MALL",
             "Address"=> "GF F11, BLDG. B., AYALA MALLS VERMOSA, DAANG HARI ROAD, IMUS CAVITE",
             "Location"=> "REGION IVA",
             "Date Open"=> "11-11-2024",
             "Status"=> "active"
            ],
        ];

        $unique_branches = array_map("unserialize", array_unique(array_map("serialize", $branches)));

        foreach($unique_branches as $branch) {
            Branch::create([
                "name" => $branch["Branch Name"],
                "branch_number" => $branch["Branch ID"],
                "status" => $branch["Status"],
                "mall_admin" => $branch["Mall Admin"],
                "short_name" => $branch["Short Name"],
                "address" => $branch["Address"],
                "location" => $branch["Location"],
                "date_open" =>  DateTime::createFromFormat('m-d-Y', $branch["Date Open"])->format('Y-m-d'),
            ]);
        }
    }
}
