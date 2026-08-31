CLEAR
USE DISKBANK
set talk off
SET BELL OFF
set status off
low=0
high=9999
auswahl= " "
taste=" "
luft=""
dummy=" "
dummyb=" "
anz=reccount ()
proz=0
gez=0

a=0
GOTO TOP
      
@ 1,10 SAY "Suchen nach Gr”áe  "
@ 2,10 SAY "            von :  "
@ $,$ get low
@ 3,10 SAY "            bis :  "
@ $,$ get high
READ
clear
@ 1,0 say "Produzent       Typ             Cyl Hds Sec       Controller   Cap    MB"
           do while .not. eof()
                                proz=gez*100/anz
                                @ 24,64 say proz
                                @ 24,79 say "%"

                if Cap < low
                    gez=gez+1
                    skip
                    else
                    if Cap > high
                    gez=gez+1
                    skip
                    else
                    @ 3+a,0  say produzent
                    @ 3+a,16 say typ   
                    @ 3+a,31 say cyl
                    @ 3+a,36 say hds
                    @ 3+a,40 say sec
                    @ 3+a,51 say controller   
                    @ 3+a,62 say Cap
                    @ 3+a,69 say mb
                    a=a+1
                    if a=17
                       a=0
                       @ 23,0 say "Seite voll ! S = Stop  Leertast = weiter"
                       @ $,$ get taste
                       read
                       tast = UPPER (taste)
                          if tast ="S"
                             exit
                             else
                             clear
                             @ 1,0 say "Produzent       Typ             Cyl Hds Sec       Controller   Cap    MB"   
                          endif
                    endif
                    
                    dummy=typ
                    gez=gez+1
                    skip
                 endif
                 endif
            ENDDO

     if dummy=dummyb
     @ 22,20 say " Platten dieser Gr”áe nicht gefunden ! "
     @ 23,10 say " "
     else
     @ 23,10 say " "  
     WAIT
     DO such
     ENDIF
     IF .NOT. eof()
     WAIT
RETURN

