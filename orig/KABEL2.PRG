CLEAR
@ 0,0 SAY "               Pin #1 on any drive cable SHOULD be indicated by a"
@ 1,0 SAY "               a colored stripe.  If you should find the stripe"
@ 2,0 SAY "               by connector pin 34 (or 20), inspect the whole"
@ 3,0 SAY "               cable VERY throughly!"
@ 4,0 SAY ""
@ 5,0 SAY "DRIVE SELECT  For both Floppy and Hard drives, when the 34 pin"
@ 6,0 SAY "JUMPERS:      cable has a twist, the device number should be set"
@ 7,0 SAY "              to the second position.  Drives numbered 0-3, set to"
@ 8,0 SAY "              1, those numbered 1-4, set to 2.  When cables with-"
@ 9,0 SAY "              out a twist are used, Floppy A , and(or) Hard drive"
@ 10,0 SAY "              C should be set to 1, and the second Floppy and"
@ 11,0 SAY "              (or) Hard drive should be set to 2."
@ 12,0 SAY " TERMINATORS: When using more than one drive on a cable (ie; 2FDs"
@ 13,0 SAY "              or 2HDs), the terminating resistor pack should be  "
@ 14,0 SAY "              left on the drive furthest from the controller, and "
@ 15,0 SAY "              removed from the drive closest to the controller."
@ 16,0 SAY "       NOTE:  On SCSI drives, the Host Adapter also has resistors.       "
@ 17,0 SAY "              These are needed to terminate both ends of the bus.        "
@ 18,0 SAY "              Since the SCSI bus can have up to 7 devices attached       "
@ 19,0 SAY "              to it, only the Host Adapter and the device farthest       "
@ 20,0 SAY "              from it will retain the resistors.  All devices in-        "
@ 21,0 SAY "              between should have theirs removed.                        "

@ 23,0 say ""
wait
return





















