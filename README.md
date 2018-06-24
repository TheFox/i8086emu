# Intel 8086 CPU Emulator

A Intel 8086 CPU Emulator written in pure PHP.

## Compile the BIOS

Run `make bios/bios`.

## TTY

In order to have a TTY for the in- and output you can specify `--tty <path>`. This will start a `socat` subprocess to create an interface between PHP and TTY. The TTY then can be accessed using `screen`.

Optional, to use a different installation path for the `socat` binary you can specify `--socat <path>`.

## Pseudo-TTY Test

1. Install `socat`.
2. Open a shell and run `./bin/screen_pipe.sh`.
3. Open another shell and run `php ./bin/ptty_test.php`.
4. Open ANOTHER shell and run `screen /dev/csTTY1`.

## Terms

- `Byte` - 8 bit, one single character.
- `Word` - 16 bit, or 2 Byte.

## 8086 Resources

- [Wikipedia: Intel 8086](https://en.wikipedia.org/wiki/Intel_8086)
- [Wikipedia: Processor Register](https://en.wikipedia.org/wiki/Processor_register)
- [Wikipedia: FLAGS Register](https://en.wikipedia.org/wiki/FLAGS_register)
- [Wikipedia: Parity Flag](https://en.wikipedia.org/wiki/Parity_flag)
- [Wikipedia: Word](https://en.wikipedia.org/wiki/Word_(computer_architecture))
- [8086 opcodes](http://www.mlsite.net/8086/)
- [StackExchange: Emulate an Intel 8086 CPU](https://codegolf.stackexchange.com/questions/4732/emulate-an-intel-8086-cpu)
- [x86 Registers](http://www.eecg.toronto.edu/~amza/www.mindsec.com/files/x86regs.html)
- [Encoding x86 Instructions](https://www-user.tu-chemnitz.de/~heha/viewchm.php/hs/x86.chm/x86.htm)
- [Encoding x86 Instruction Operands, MOD-REG-R/M Byte](http://www.c-jump.com/CIS77/CPU/x86/X77_0060_mod_reg_r_m_byte.htm)
- [X86 Assembly/X86 Architecture](https://en.wikibooks.org/wiki/X86_Assembly/X86_Architecture)
- [X86-64 Instruction Encoding](http://wiki.osdev.org/X86-64_Instruction_Encoding)
- [OUT -- Output to Port](https://pdos.csail.mit.edu/6.828/2010/readings/i386/OUT.htm)
- [MDA, CGA, HGC, EGA, VGA, SVGA, TIGA](https://www.tu-chemnitz.de/informatik/RA/news/stack/kompendium/vortraege_98/grafik/adaptertypen.html) (German)
- [8086/88 Assembler Befehlsreferenz](http://www.i8086.de/asm/8086-88-asm.html) (German)
- [X86 Opcode and Instruction Reference](http://ref.x86asm.net/coder32.html)
- [Understanding Intel Instruction Sizes](https://www.swansontec.com/sintel.html)

## More Resources

- [How To Write a Computer Emulator](https://fms.komkon.org/EMUL8/HOWTO.html)
