CLEAR
USE DISKBANK
set talk off
SET BELL OFF
set status off
anz= reccount ()
proz=0
gez=0
auswahl= " "
taste=" "
luft=""
dummy=" "
dummyb=" "
Nummer = 0
a=0
GOTO TOP
      
@ 1,10 SAY "Typenbezeichnung eingeben : "
ty=space(25)
@ $,$ GET ty
READ
AAA = UPPER (ty)
                
clear
@ 1,0 say "Satz  Produzent       Typ             Cyl Hds Sec       Controller   Cap    MB"
aaa = trim (AAA)                 
           do while .not. eof()
              proz=gez*100/anz
              @ 24,64 say proz
              @ 24,79 say "%"
                 if typ=dummy 
                   gez=gez+1
                   skip
                   enddo
                 endif
              if at(AAA,typ) = 0      
                 gez=gez+1
                 skip
                 else
                 @ 3+a,0  say satz
                 @ 3+a,6  say produzent
                 @ 3+a,22 say typ   
                 @ 3+a,37 say cyl
                 @ 3+a,42 say hds
                 @ 3+a,46 say sec
                 @ 3+a,57 say controller   
                 @ 3+a,68 say Cap
                 @ 3+a,75 say mb
                 

                 a=a+1
                 if a=17
                    a=0
                    @ 23,0 say "Seite voll ! S = Stop  Leertast = weiter  M = Satz merken"
                    @ $,$ get taste
                    read
                    tast = UPPER (taste)
                        DO CASE
                                CASE tast ="S"
                                        exit
                                CASE tast ="M"
                                        @ 23,0 say "Satznummer eingeben :                                     "
                                        @ 24,0 get Nummer
                                        read
                                        exit

                                otherwise
                                clear
                                @ 1,0 say "Satz  Produzent       Typ             Cyl Hds Sec       Controller   Cap    MB"   
                 
                 endif
                        dummy=typ
                gez=gez+1
                skip
                
              endif
            ENDDO

     if dummy=dummyb
     @ 22,20 say " Typ v”llig unbekannt ! "
     @ 23,10 say " "
     WAIT
     do such
     
     else
     @ 23,10 say " "  
     WAIT
     DO such
     ENDIF
     IF .NOT. eof()
     WAIT
RETURN

