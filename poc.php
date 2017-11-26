<?php

// Proof of concept

// Emulator system constants
const IO_PORT_COUNT = 0x10000;
const RAM_SIZE = 0x10FFF0;
const REGS_BASE = 0xF0000;
const VIDEO_RAM_SIZE = 0x10000;

// 16-bit register decodes
const REG_AX =0;
const REG_CX =1;
const REG_DX =2;
const REG_BX =3;
const REG_SP =4;
const REG_BP =5;
const REG_SI =6;
const REG_DI =7;

const REG_ES =8;
const REG_CS =9;
const REG_SS =10;
const REG_DS =11;

const REG_ZERO =12;
const REG_SCRATCH =13;

// 8-bit register decodes
const REG_AL =0;
const REG_AH =1;
const REG_CL =2;
const REG_CH =3;
const REG_DL =4;
const REG_DH =5;
const REG_BL =6;
const REG_BH =7;

// FLAGS register decodes
const FLAG_CF =40;
const FLAG_PF =41;
const FLAG_AF =42;
const FLAG_ZF =43;
const FLAG_SF =44;
const FLAG_TF =45;
const FLAG_IF =46;
const FLAG_DF =47;
const FLAG_OF =48;

require_once __DIR__ . '/vendor/autoload.php';

$bios = $argv[1];
