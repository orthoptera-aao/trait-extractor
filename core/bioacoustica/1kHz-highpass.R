library(tuneR)
library(seewave)


args = commandArgs(trailingOnly=TRUE)

recording_id <- args[1]
filename <- args[2];

wave <- readWave(filename)
wave<-ffilter(wave, from=1000, to=wave@samp.rate/2, output="Wave")
savewav(wave, f=wave@samp.rate, filename=paste0("scratch/wav/",recording_id,".1kHz-highpass.wav"))