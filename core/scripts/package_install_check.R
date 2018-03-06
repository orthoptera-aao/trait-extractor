args = commandArgs(trailingOnly=TRUE)
  if (is.element(args[1], installed.packages()[,1])) {
    quit("no", status=0, runLast=FALSE)
  } else {
    quit("no", status=1, runLast=FALSE)
  }
