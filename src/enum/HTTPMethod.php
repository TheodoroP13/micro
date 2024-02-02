<?php

namespace Prospera\Enumerators;

enum HTTPMethod : int {
    case GET            = 1;
    case POST           = 2;
    case PUT            = 3;
    case DELETE         = 4;
}