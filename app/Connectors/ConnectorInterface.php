<?php
interface ConnectorInterface {
  public function code(): string;
  public function name(): string;
  public function category(): string;
  public function status(): string;
  public function capabilities(): array;
  public function requirements(): array;
  public function health(): array;
  public function operations(): array;
  public function risks(): array;
}
