
all: bios/bios

bios/bios: bios/bios.asm
	nasm $<
