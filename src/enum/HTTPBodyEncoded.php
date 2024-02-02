<?php

namespace Prospera\Enumerators;

enum HTTPBodyEncoded : int {
    case JSON            = 1;
    case URLEncoded      = 2;
    case Multipart 		 = 3;
}