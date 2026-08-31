CLEAR
SET TALK OFF
SET STATUS OFF

B=512
S=17
H=8
C=1024
START=0
z=-2
tast2=" "

@ 1,5 say "Suche des besten Biostyps fr eine IDE-Platte           "

	if Nummer >0
		use diskbank
		goto record Nummer
	
	@ 2,5  say "Datensatz Nummer          "
	@ 2,29 say Nummer
	@ 2,42 say "bernehmen J/N ? "
	@ 3,5  say produzent
	@ 3,15 say typ
	@ 2,60 get tast2
	read
	tas2 = UPPER (tast2)
	if tas2="J"                
		S=SEC
		H=HDS
		C=CYL
		endif
	@ 2,2 say "                                                                     "
	@ 3,2 say "                                                                     "
	
	set color to g/n
	@ 5,30 say produzent
	@ 5,50 say typ
	set color to w/n
	endif

USE SECALL
GO BOTTOM

@ 3,2 SAY "SECTORS/TRACK "
@ 3,17 GET S
READ 

@ 4,2 SAY "HEADS"
@ 4,17 GET H
READ

@ 5,2 SAY "CYLINDER"
@ 5,17 GET C
READ

@ 6,2 SAY "BYTE/SEKTOR"
@ 6,17 GET B
READ


MEGS= LTRIM(STR(B*S*H*C / 1048576 ,,2))
SECT= S*H*C
@ 3,30 say "MBYTE (1024*1024 BYTE)"  
@ 3,55 SAY MEGS
@ 4,30 SAY "SEKTOREN GESAMT   "  
@ 4,51 SAY SECT
*-----------------------------------------------------------------
@ 7,0 SAY "                                                                   VERLUST "
@ 8,0 SAY "BIOS      NR    DATE     CYL  HDS  SEC   MB    PRE  LAND           MB     % "
POINTER= RECNO()
	DO WHILE POINTER > 1
		
		IF SEKTORS_AL > SECT
		POINTER=POINTER -1 
		GO POINTER

		ELSE
		START=START+1
		LOOSE=SECT-SEKTORS_AL
		LOOSEKB= LOOSE*512 / 1024 / 1024
		LOOSEPC=LOOSE * 100 / SECT        
		 LE=STR(LOOSE)
		 LEK=LTRIM(LE)
		 LEKB=STR(LOOSEKB,,2)       
		 LEKBK=LTRIM(LEKB)
		 LEPC=STR(LOOSEPC,,2)       
		 LEPCK=LTRIM(LEPC)
		 PMB=TRACKS*HEADS*SEKTORS_TR*512 / 1024/1024
		 PMBK=LTRIM(STR(PMB,,0))
		    if loosepc > 10
			set color to r/n
			@ 6,30 SAY "Manuelle Parameter empfohlen !" 
			set color to w/n
			endif


			@ 12+z,0 SAY BIOS 
			@ 12+z,10 SAY TYP 
			@ 12+z,14 SAY DATE 
			@ 12+z,24 SAY TRACKS 
			@ 12+z,30 SAY HEADS 
			@ 12+z,34 SAY SEKTORS_TR  
			@ 12+Z,41 SAY PMBK
			@ 12+z,45 SAY PRE  
			@ 12+z,52 SAY LANDING
*                       @ 12+z,50 say SEKTORS_AL
*                       @ 12+Z,59 SAY LEK
			@ 12+Z,66 SAY LEKBK
			@ 12+Z,73 SAY LEPCK
		POINTER=POINTER -1 
		z=z+1
		GO POINTER                 
			
			IF START > 12
			@ 23, 0
			WAIT
			RETURN
			ENDIF
		ENDIF



	ENDDO
