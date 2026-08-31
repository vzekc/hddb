CLEAR
USE biostyp
GO top
SET TALK OFF
SET STATUS OFF
auswahl= " "    
z=1
start=0
@ 1,2 say "Anzeige der Biostypen eines AT "
@ 3,2 SAY "(1) American Megatrends Inc.  "
@ 4,2 SAY "(2) Compaq"
@ 5,2 SAY "(3) Award"
@ 6,2 SAY "(4) IBM "
@ 7,2 SAY "(5) IBM PS2 (Typ 1 - 23 entspricht IBM)"
@ 8,2 SAY "(6) Phoenix"
@ 9,2 SAY "(7) HP-Phoenix"
@ 11,2 SAY "(Z) Zurck zum Hauptmenue"
@ $,$ GET auswahl
     read
     aw = UPPER (auswahl)

if aw="1"
	tempp="ami     "
endif
if aw="2"
	tempp="compaq  "
endif
if aw="3"
	tempp="award   "
endif
if aw="4"
	tempp="ibm     "
endif
if aw="5"
	tempp="ibm ps2 "
endif
if aw="6"
        tempp="phoenix "
endif
if aw="7"
        tempp="hp      "
endif
if aw="Z"
       return
endif
if aw=" "
       return
endif
tp = UPPER (tempp)
CLEAR
@ 0,0 SAY "BIOS      NR    DATE     CYL  HDS  SEC   MB    PRE  LAND"
DO WHILE .not. eof()
		IF bios=tp
			start=start+1
			PMB=TRACKS*HEADS*SEKTORS_TR*512 / 1024/1024
			PMBK=LTRIM(STR(PMB,,0))
			@ 1+z,0 SAY BIOS 
			@ 1+z,9 SAY TYP 
			@ 1+z,13 SAY DATE 
			@ 1+z,23 SAY TRACKS 
			@ 1+z,29 SAY HEADS 
			@ 1+z,33 SAY SEKTORS_TR  
			@ 1+Z,40 SAY PMBK
			@ 1+z,44 SAY PRE  
			@ 1+z,51 SAY LANDING
			skip
			z=z+1
				
				IF START > 19
				@ 23, 0
				WAIT
				clear
				@ 0,0 SAY "BIOS      NR    DATE     CYL  HDS  SEC   MB    PRE  LAND"
				Z=1
				START=0
				ENDIF
			else
			skip
		
		ENDIF
ENDDO
@ 23, 0 
wait
clear
goto top
return
