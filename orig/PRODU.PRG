CLEAR
USE herst
set talk off
@ 3,18 TO 9,52 double
anz=reccount ()
proz=0
gez=0

auswahl= " "
dummy=" "
dummyb=" "
a=0
GOTO TOP
      
      @ 5,20 SAY "Gesuchten Produzent eingeben"
      produz=space(15)
      @ 7,20 GET produz
      READ
      if produz = dummy
	return
	else
	AAA = UPPER (produz)
	LOCATE FOR PRODUZENT $AAA
	endif
      IF eof()
      @ 10,20 SAY "Produzent in dieser Schreibweise nicht gefunden"
      @ 11,20 SAY "aber...."
      @ 14,20 say " "             
		goto top
		aaa = trim (AAA)                 
		do while .not. eof()
				proz=gez*100/anz
				@ 24,64 say proz
				@ 24,79 say "%"
			
			if produzent=dummy 
			gez=gez+1
			skip
			endif        
				if at(AAA,produzent) = 0      
				gez=gez+1
				skip
				else
				@ 14+a,20 say Produzent 
				@ 14+a,40 say "...oder.. "
				a=a+1
				if a > 8 
				wait 
				return
				endif

				dummy=produzent
				skip                    
				endif
		ENDDO
		@ 22,0 say ""
		wait 
		goto top
		return


      ELSE
      @ 23,20 say " Produzent bekannt ! "   
      WAIT
      RETURN
      endif
	
	@ 23,20 say " Produzent unbekannt ! "
	@ 24,10 say " "
	WAIT
	return


